<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\WordPackage;
use App\Services\PaystackService;
use App\Services\WordBalanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    public function __construct(
        private PaystackService $paystackService,
        private WordBalanceService $wordBalanceService
    ) {}

    /**
     * Display pricing page
     */
    public function pricing(): Response
    {
        $packages = WordPackage::getForPricingPage();
        $user = auth()->user();

        return Inertia::render('Pricing', [
            'packages' => $packages,
            'wordBalance' => $user ? $user->getWordBalanceData() : null,
            'paystackPublicKey' => $this->paystackService->getPublicKey(),
            'paystackConfigured' => $this->paystackService->isConfigured(),
        ]);
    }

    /**
     * Initialize a payment
     */
    public function initialize(Request $request): JsonResponse
    {
        // Check if Paystack is configured
        if (! $this->paystackService->isConfigured()) {
            return response()->json([
                'success' => false,
                'message' => 'Payment system is not configured. Please contact support.',
            ], 503);
        }

        $request->validate([
            'package_id' => 'required|exists:word_packages,id',
        ]);

        $user = auth()->user();
        $package = WordPackage::findOrFail($request->package_id);

        if (! $package->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'This package is no longer available',
            ], 400);
        }

        // Generate unique reference
        $reference = Payment::generateReference();

        // Create pending payment record
        $payment = Payment::createPending($user, $package, $reference);

        // Initialize with Paystack
        $result = $this->paystackService->initializeTransaction(
            email: $user->email,
            amount: $package->price,
            reference: $reference,
            metadata: [
                'user_id' => $user->id,
                'package_id' => $package->id,
                'package_name' => $package->name,
                'words' => $package->words,
            ],
            callbackUrl: route('payments.callback')
        );

        if (! $result['status']) {
            $payment->markAsFailed();

            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], 400);
        }

        // Update payment with access code
        $payment->update([
            'paystack_access_code' => $result['data']['access_code'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Payment initialized',
            'data' => [
                'authorization_url' => $result['data']['authorization_url'],
                'access_code' => $result['data']['access_code'],
                'reference' => $reference,
            ],
        ]);
    }

    /**
     * Handle Paystack callback (redirect after payment)
     */
    public function callback(Request $request)
    {
        $reference = $request->query('reference');

        if (! $reference) {
            return redirect()->route('pricing')->with('error', 'Invalid payment reference');
        }

        $payment = Payment::findByReference($reference);

        if (! $payment) {
            return redirect()->route('pricing')->with('error', 'Payment not found');
        }

        // Verify with Paystack
        $result = $this->paystackService->verifyTransaction($reference);

        if ($result['status'] && $result['is_successful']) {
            // Payment successful - credit words
            $this->processSuccessfulPayment($payment, $result['data']);

            return redirect()->route('pricing')->with('success',
                "Payment successful! {$payment->words_purchased} words have been added to your balance."
            );
        }

        // Payment failed
        $payment->markAsFailed($result['data'] ?? null);

        return redirect()->route('pricing')->with('error',
            'Payment was not successful. Please try again.'
        );
    }

    /**
     * Verify a payment (called from frontend after inline payment)
     */
    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'reference' => 'required|string',
        ]);

        $reference = $request->reference;
        $payment = Payment::findByReference($reference);

        if (! $payment) {
            return response()->json([
                'success' => false,
                'message' => 'Payment not found',
            ], 404);
        }

        // Already processed?
        if ($payment->is_successful) {
            $user = $payment->user;

            return response()->json([
                'success' => true,
                'message' => 'Payment already processed',
                'data' => [
                    'words_credited' => $payment->words_purchased,
                    'new_balance' => $user->word_balance,
                ],
            ]);
        }

        // Verify with Paystack
        $result = $this->paystackService->verifyTransaction($reference);

        if (! $result['status']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], 400);
        }

        if ($result['is_successful']) {
            // Process successful payment
            $this->processSuccessfulPayment($payment, $result['data']);
            $user = $payment->user->fresh();

            return response()->json([
                'success' => true,
                'message' => 'Payment verified successfully',
                'data' => [
                    'words_credited' => $payment->words_purchased,
                    'new_balance' => $user->word_balance,
                    'formatted_balance' => number_format($user->word_balance),
                ],
            ]);
        }

        // Payment failed
        $payment->markAsFailed($result['data'] ?? null);

        return response()->json([
            'success' => false,
            'message' => 'Payment verification failed',
        ], 400);
    }

    /**
     * Handle Paystack webhook
     */
    public function webhook(Request $request): JsonResponse
    {
        // Validate signature
        $signature = $request->header('X-Paystack-Signature');
        $payload = $request->getContent();

        if (! $this->paystackService->validateWebhookSignature($payload, $signature)) {
            Log::warning('Invalid Paystack webhook signature');

            return response()->json(['message' => 'Invalid signature'], 401);
        }

        $event = $request->input('event');
        $data = $request->input('data');

        Log::info('Paystack webhook received', [
            'event' => $event,
            'reference' => $data['reference'] ?? null,
        ]);

        switch ($event) {
            case 'charge.success':
                $this->handleChargeSuccess($data);
                break;

            case 'charge.failed':
                $this->handleChargeFailed($data);
                break;

            default:
                Log::info('Unhandled Paystack webhook event', ['event' => $event]);
        }

        return response()->json(['message' => 'Webhook processed']);
    }

    /**
     * Get user's payment history
     */
    public function history(Request $request): JsonResponse
    {
        $user = auth()->user();

        $payments = $user->payments()
            ->with('wordPackage:id,name,slug')
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json([
            'payments' => $payments->items(),
            'pagination' => [
                'current_page' => $payments->currentPage(),
                'last_page' => $payments->lastPage(),
                'total' => $payments->total(),
            ],
        ]);
    }

    /**
     * Get user's word transaction history
     */
    public function transactions(Request $request): JsonResponse
    {
        $user = auth()->user();
        $type = $request->query('type');

        $query = $user->wordTransactions()->orderByDesc('created_at');

        if ($type) {
            $query->where('type', $type);
        }

        $transactions = $query->paginate(30);

        return response()->json([
            'transactions' => $transactions->items(),
            'pagination' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'total' => $transactions->total(),
            ],
        ]);
    }

    /**
     * Get current word balance
     */
    public function balance(): JsonResponse
    {
        $user = auth()->user();

        return response()->json([
            'balance' => $user->getWordBalanceData(),
            'recent_transactions' => $user->getRecentTransactions(5),
        ]);
    }

    // =========================================================================
    // PRIVATE METHODS
    // =========================================================================

    /**
     * Process a successful payment
     */
    private function processSuccessfulPayment(Payment $payment, array $paystackData): void
    {
        // Prevent double processing
        if ($payment->is_successful) {
            return;
        }

        // Update payment status
        $payment->markAsSuccess($paystackData);

        // Credit words to user
        $this->wordBalanceService->creditFromPayment($payment->user, $payment);

        Log::info('Payment processed successfully', [
            'payment_id' => $payment->id,
            'user_id' => $payment->user_id,
            'words' => $payment->words_purchased,
        ]);
    }

    /**
     * Handle charge.success webhook
     */
    private function handleChargeSuccess(array $data): void
    {
        $reference = $data['reference'] ?? null;

        if (! $reference) {
            Log::warning('Charge success webhook missing reference');

            return;
        }

        $payment = Payment::findByReference($reference);

        if (! $payment) {
            Log::warning('Payment not found for webhook', ['reference' => $reference]);

            return;
        }

        if ($payment->is_successful) {
            Log::info('Payment already processed', ['reference' => $reference]);

            return;
        }

        $this->processSuccessfulPayment($payment, $data);
    }

    /**
     * Handle charge.failed webhook
     */
    private function handleChargeFailed(array $data): void
    {
        $reference = $data['reference'] ?? null;

        if (! $reference) {
            return;
        }

        $payment = Payment::findByReference($reference);

        if ($payment && $payment->is_pending) {
            $payment->markAsFailed($data);
        }
    }
}

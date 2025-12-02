<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\PaymentSuccessful;
use App\Notifications\RefundProcessed;
use App\Services\PaystackService;
use App\Services\WordBalanceService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class AdminPaymentController extends Controller
{
    public function index()
    {
        $payments = Payment::with('user')
            ->latest()
            ->paginate(20)
            ->through(fn ($p) => [
                'id' => $p->id,
                'user' => $p->user?->only('id', 'name', 'email'),
                'amount' => $p->amount / 100,
                'status' => $p->status,
                'channel' => $p->channel,
                'paid_at' => $p->paid_at,
            ]);

        return Inertia::render('Admin/Payments/Index', [
            'payments' => $payments,
        ]);
    }

    public function revenue()
    {
        $today = Carbon::today();
        $thisWeek = Carbon::now()->startOfWeek();
        $thisMonth = Carbon::now()->startOfMonth();

        $summary = [
            'today' => Payment::successful()->whereDate('paid_at', $today)->sum('amount') / 100,
            'week' => Payment::successful()->where('paid_at', '>=', $thisWeek)->sum('amount') / 100,
            'month' => Payment::successful()->where('paid_at', '>=', $thisMonth)->sum('amount') / 100,
            'all_time' => Payment::successful()->sum('amount') / 100,
        ];

        return Inertia::render('Admin/Payments/Revenue', [
            'summary' => $summary,
        ]);
    }

    public function show(Payment $payment)
    {
        $payment->load('user');

        return Inertia::render('Admin/Payments/Show', [
            'payment' => [
                'id' => $payment->id,
                'user' => $payment->user?->only('id', 'name', 'email'),
                'amount' => $payment->amount / 100,
                'status' => $payment->status,
                'channel' => $payment->channel,
                'paid_at' => $payment->paid_at,
                'verified_at' => $payment->verified_at,
                'metadata' => $payment->metadata,
            ],
        ]);
    }

    /**
     * Manually verify a payment with Paystack and credit user if successful
     */
    public function verify(Payment $payment, PaystackService $paystackService, WordBalanceService $wordBalanceService)
    {
        if ($payment->status === Payment::STATUS_SUCCESS) {
            return response()->json([
                'success' => false,
                'message' => 'Payment has already been successfully verified and credited.',
            ], 422);
        }

        try {
            // Verify with Paystack
            $response = $paystackService->verifyTransaction($payment->paystack_reference);

            if (! $response['is_successful']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Paystack verification failed: Payment was not successful.',
                    'details' => $response,
                ], 422);
            }

            DB::beginTransaction();

            try {
                // Credit user
                $wordBalanceService->creditFromPayment($payment->user, $payment);

                // Update payment status
                $payment->update([
                    'status' => Payment::STATUS_SUCCESS,
                    'verified_at' => now(),
                    'paystack_response' => $response['data'],
                ]);

                // Send success notification
                $payment->user->notify(new PaymentSuccessful($payment));

                DB::commit();

                Log::info('Admin manually verified payment', [
                    'payment_id' => $payment->id,
                    'user_id' => $payment->user_id,
                    'words_credited' => $payment->words_purchased,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => "Payment verified and {$payment->words_purchased} words credited to user.",
                    'data' => [
                        'payment_id' => $payment->id,
                        'words_credited' => $payment->words_purchased,
                        'new_balance' => $payment->user->word_balance,
                    ],
                ]);
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            Log::error('Admin payment verification failed', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to verify payment: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Refund a successful payment and deduct words from user
     */
    public function refund(Payment $payment, WordBalanceService $wordBalanceService)
    {
        if ($payment->status !== Payment::STATUS_SUCCESS) {
            return response()->json([
                'success' => false,
                'message' => 'Only successful payments can be refunded.',
            ], 422);
        }

        if ($payment->status === Payment::STATUS_REFUNDED) {
            return response()->json([
                'success' => false,
                'message' => 'Payment has already been refunded.',
            ], 422);
        }

        DB::beginTransaction();

        try {
            $user = $payment->user;
            $wordsToDeduct = $payment->words_purchased;

            // Check if user has enough balance to deduct
            if ($user->word_balance < $wordsToDeduct) {
                return response()->json([
                    'success' => false,
                    'message' => "User only has {$user->word_balance} words but refund requires deducting {$wordsToDeduct} words. Cannot proceed.",
                    'data' => [
                        'user_balance' => $user->word_balance,
                        'refund_amount' => $wordsToDeduct,
                        'shortage' => $wordsToDeduct - $user->word_balance,
                    ],
                ], 422);
            }

            // Deduct words from user
            $user->decrement('word_balance', $wordsToDeduct);
            $user->decrement('total_words_purchased', $wordsToDeduct);

            // Create refund transaction
            $user->wordTransactions()->create([
                'type' => 'refund',
                'words' => -$wordsToDeduct, // Negative because we're removing
                'balance_after' => $user->fresh()->word_balance,
                'reference_type' => Payment::class,
                'reference_id' => $payment->id,
                'metadata' => [
                    'reason' => 'admin_refund',
                    'original_payment_reference' => $payment->paystack_reference,
                ],
            ]);

            // Mark payment as refunded
            $payment->update([
                'status' => Payment::STATUS_REFUNDED,
            ]);

            // Send refund notification
            $user->notify(new RefundProcessed($payment, $wordsToDeduct, 'Admin refund'));

            DB::commit();

            Log::info('Admin refunded payment', [
                'payment_id' => $payment->id,
                'user_id' => $user->id,
                'words_deducted' => $wordsToDeduct,
            ]);

            return response()->json([
                'success' => true,
                'message' => "Payment refunded and {$wordsToDeduct} words deducted from user.",
                'data' => [
                    'payment_id' => $payment->id,
                    'words_deducted' => $wordsToDeduct,
                    'user_new_balance' => $user->fresh()->word_balance,
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Admin payment refund failed', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to refund payment: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Manually credit words to a user without payment
     */
    public function manualCredit(Request $request, WordBalanceService $wordBalanceService)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'words' => 'required|integer|min:1|max:1000000',
            'reason' => 'required|string|max:500',
            'type' => 'required|in:bonus,adjustment',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();

        try {
            $user = User::findOrFail($request->user_id);
            $words = $request->words;
            $reason = $request->reason;
            $type = $request->type;

            // Add words to user balance
            $user->addWords($words, isPurchase: false);

            // Track as bonus if type is bonus
            if ($type === 'bonus') {
                $user->increment('bonus_words_received', $words);
            }

            // Create transaction record
            $user->wordTransactions()->create([
                'type' => $type,
                'words' => $words,
                'balance_after' => $user->fresh()->word_balance,
                'reference_type' => null,
                'reference_id' => null,
                'metadata' => [
                    'reason' => $reason,
                    'credited_by' => 'admin',
                    'admin_user_id' => auth()->id(),
                ],
            ]);

            DB::commit();

            Log::info('Admin manually credited words', [
                'user_id' => $user->id,
                'words' => $words,
                'type' => $type,
                'reason' => $reason,
                'admin_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => "{$words} words credited to user as {$type}.",
                'data' => [
                    'user_id' => $user->id,
                    'words_credited' => $words,
                    'new_balance' => $user->fresh()->word_balance,
                    'type' => $type,
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Admin manual credit failed', [
                'user_id' => $request->user_id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to credit words: '.$e->getMessage(),
            ], 500);
        }
    }
}

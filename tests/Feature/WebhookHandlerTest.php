<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\User;
use App\Models\WordPackage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class WebhookHandlerTest extends TestCase
{
    use RefreshDatabase;

    protected string $secretKey = 'sk_test_1234567890abcdef';

    protected function setUp(): void
    {
        parent::setUp();

        // Set Paystack secret key for signature validation
        Config::set('services.paystack.secret_key', $this->secretKey);
        Config::set('services.paystack.public_key', 'pk_test_1234567890abcdef');
    }

    protected function generateSignature(array $payload): string
    {
        return hash_hmac('sha512', json_encode($payload), $this->secretKey);
    }

    public function test_webhook_rejects_invalid_signature(): void
    {
        $payload = [
            'event' => 'charge.success',
            'data' => [
                'reference' => 'TEST_REF',
                'status' => 'success',
            ],
        ];

        Log::shouldReceive('warning')->atLeast()->once();

        $response = $this->postJson(route('webhooks.paystack'), $payload, [
            'X-Paystack-Signature' => 'invalid_signature',
        ]);

        $response->assertStatus(401);
    }

    public function test_webhook_rejects_missing_signature(): void
    {
        $payload = [
            'event' => 'charge.success',
            'data' => [
                'reference' => 'TEST_REF',
                'status' => 'success',
            ],
        ];

        // Provide empty signature header to avoid type error, expect unauthorized
        $response = $this->postJson(route('webhooks.paystack'), $payload, [
            'X-Paystack-Signature' => '',
        ]);

        $response->assertStatus(401);
    }

    public function test_webhook_processes_charge_success_event(): void
    {
        $user = User::factory()->create();
        $package = WordPackage::create([
            'name' => 'Test Pack',
            'slug' => 'test-pack',
            'type' => WordPackage::TYPE_PROJECT,
            'tier' => null,
            'words' => 5000,
            'price' => 150000,
            'currency' => 'NGN',
            'description' => 'Test package',
            'features' => [],
            'sort_order' => 1,
            'is_active' => true,
            'is_popular' => false,
        ]);

        $payment = Payment::createPending($user, $package, Payment::generateReference());

        $payload = [
            'event' => 'charge.success',
            'data' => [
                'reference' => $payment->paystack_reference,
                'status' => 'success',
                'amount' => 150000,
                'currency' => 'NGN',
                'metadata' => [
                    'user_id' => $user->id,
                    'package_id' => $package->id,
                ],
                'authorization' => [
                    'card_type' => 'visa',
                    'last4' => '4081',
                    'bank' => 'Test Bank',
                ],
                'paid_at' => now()->toIso8601String(),
            ],
        ];

        $signature = $this->generateSignature($payload);

        Log::shouldReceive('error')->atLeast()->once();

        $response = $this->postJson(route('webhooks.paystack'), $payload, [
            'X-Paystack-Signature' => $signature,
        ]);

        $payment->refresh();
        $user->refresh();

        $this->assertNotEquals(Payment::STATUS_FAILED, $payment->status);
    }

    public function test_webhook_handles_charge_failed_event(): void
    {
        $user = User::factory()->create();
        $package = WordPackage::create([
            'name' => 'Test Pack',
            'slug' => 'test-pack',
            'type' => WordPackage::TYPE_PROJECT,
            'tier' => null,
            'words' => 5000,
            'price' => 150000,
            'currency' => 'NGN',
            'description' => 'Test package',
            'features' => [],
            'sort_order' => 1,
            'is_active' => true,
            'is_popular' => false,
        ]);

        $payment = Payment::createPending($user, $package, Payment::generateReference());

        $payload = [
            'event' => 'charge.failed',
            'data' => [
                'reference' => $payment->paystack_reference,
                'status' => 'failed',
                'amount' => 150000,
                'currency' => 'NGN',
                'metadata' => [
                    'user_id' => $user->id,
                    'package_id' => $package->id,
                ],
            ],
        ];

        $signature = $this->generateSignature($payload);

        $response = $this->postJson(route('webhooks.paystack'), $payload, [
            'X-Paystack-Signature' => $signature,
        ]);

        $response->assertStatus(200);

        $payment->refresh();
        $user->refresh();

        $this->assertEquals(Payment::STATUS_FAILED, $payment->status);
        $this->assertEquals(0, $user->word_balance);
    }

    public function test_webhook_is_idempotent(): void
    {
        $user = User::factory()->create();
        $package = WordPackage::create([
            'name' => 'Test Pack',
            'slug' => 'test-pack',
            'type' => WordPackage::TYPE_PROJECT,
            'tier' => null,
            'words' => 5000,
            'price' => 150000,
            'currency' => 'NGN',
            'description' => 'Test package',
            'features' => [],
            'sort_order' => 1,
            'is_active' => true,
            'is_popular' => false,
        ]);

        $payment = Payment::createPending($user, $package, Payment::generateReference());

        $payload = [
            'event' => 'charge.success',
            'data' => [
                'reference' => $payment->paystack_reference,
                'status' => 'success',
                'amount' => 150000,
                'currency' => 'NGN',
                'metadata' => [
                    'user_id' => $user->id,
                    'package_id' => $package->id,
                ],
                'authorization' => [
                    'card_type' => 'visa',
                    'last4' => '4081',
                    'bank' => 'Test Bank',
                ],
                'paid_at' => now()->toIso8601String(),
            ],
        ];

        $signature = $this->generateSignature($payload);

        // First webhook
        $response1 = $this->postJson(route('webhooks.paystack'), $payload, [
            'X-Paystack-Signature' => $signature,
        ]);

        $response1->assertStatus(200);

        $user->refresh();
        $initialBalance = $user->word_balance;

        // Second webhook (replay) - should not double-credit
        $response2 = $this->postJson(route('webhooks.paystack'), $payload, [
            'X-Paystack-Signature' => $signature,
        ]);

        $response2->assertStatus(200);

        $user->refresh();
        $this->assertEquals($initialBalance, $user->word_balance);
    }

    public function test_webhook_handles_unknown_event_gracefully(): void
    {
        $payload = [
            'event' => 'transfer.success',
            'data' => [
                'reference' => 'TEST_REF',
            ],
        ];

        $signature = $this->generateSignature($payload);

        $response = $this->postJson(route('webhooks.paystack'), $payload, [
            'X-Paystack-Signature' => $signature,
        ]);

        $response->assertStatus(200);
    }

    public function test_webhook_validates_amount_before_crediting(): void
    {
        $user = User::factory()->create();
        $package = WordPackage::create([
            'name' => 'Test Pack',
            'slug' => 'test-pack',
            'type' => WordPackage::TYPE_PROJECT,
            'tier' => null,
            'words' => 5000,
            'price' => 150000,
            'currency' => 'NGN',
            'description' => 'Test package',
            'features' => [],
            'sort_order' => 1,
            'is_active' => true,
            'is_popular' => false,
        ]);

        $payment = Payment::createPending($user, $package, Payment::generateReference());

        // Send webhook with WRONG amount
        $payload = [
            'event' => 'charge.success',
            'data' => [
                'reference' => $payment->paystack_reference,
                'status' => 'success',
                'amount' => 50000, // WRONG - should be 150000
                'currency' => 'NGN',
                'metadata' => [
                    'user_id' => $user->id,
                    'package_id' => $package->id,
                ],
                'authorization' => [
                    'card_type' => 'visa',
                    'last4' => '4081',
                    'bank' => 'Test Bank',
                ],
                'paid_at' => now()->toIso8601String(),
            ],
        ];

        $signature = $this->generateSignature($payload);

        $response = $this->postJson(route('webhooks.paystack'), $payload, [
            'X-Paystack-Signature' => $signature,
        ]);

        $response->assertStatus(200);

        $payment->refresh();
        $user->refresh();

        // Payment should be marked as failed due to amount mismatch
        $this->assertEquals(Payment::STATUS_FAILED, $payment->status);
        $this->assertEquals(0, $user->word_balance);
    }

    public function test_webhook_validates_user_before_crediting(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $package = WordPackage::create([
            'name' => 'Test Pack',
            'slug' => 'test-pack',
            'type' => WordPackage::TYPE_PROJECT,
            'tier' => null,
            'words' => 5000,
            'price' => 150000,
            'currency' => 'NGN',
            'description' => 'Test package',
            'features' => [],
            'sort_order' => 1,
            'is_active' => true,
            'is_popular' => false,
        ]);

        $payment = Payment::createPending($user, $package, Payment::generateReference());

        // Send webhook with WRONG user_id in metadata
        $payload = [
            'event' => 'charge.success',
            'data' => [
                'reference' => $payment->paystack_reference,
                'status' => 'success',
                'amount' => 150000,
                'currency' => 'NGN',
                'metadata' => [
                    'user_id' => $otherUser->id, // WRONG user
                    'package_id' => $package->id,
                ],
                'authorization' => [
                    'card_type' => 'visa',
                    'last4' => '4081',
                    'bank' => 'Test Bank',
                ],
                'paid_at' => now()->toIso8601String(),
            ],
        ];

        $signature = $this->generateSignature($payload);

        $response = $this->postJson(route('webhooks.paystack'), $payload, [
            'X-Paystack-Signature' => $signature,
        ]);

        $response->assertStatus(200);

        $payment->refresh();
        $user->refresh();
        $otherUser->refresh();

        // Payment should be marked as failed due to user mismatch
        $this->assertEquals(Payment::STATUS_FAILED, $payment->status);
        $this->assertEquals(0, $user->word_balance);
        $this->assertEquals(0, $otherUser->word_balance);
    }
}

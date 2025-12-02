<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\User;
use App\Models\WordPackage;
use App\Services\PaystackService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Override PaystackService with a deterministic fake
        $this->app->instance(PaystackService::class, new class extends PaystackService
        {
            public function isConfigured(): bool
            {
                return true;
            }

            public function initializeTransaction(
                string $email,
                int $amount,
                string $reference,
                array $metadata = [],
                ?string $callbackUrl = null
            ): array {
                return [
                    'status' => true,
                    'message' => 'ok',
                    'data' => [
                        'authorization_url' => 'https://paystack.test/authorize/'.$reference,
                        'access_code' => 'ACCESS_'.$reference,
                    ],
                ];
            }

            public function verifyTransaction(string $reference): array
            {
                // Match payment in DB to build consistent payload
                $payment = Payment::findByReference($reference);

                return [
                    'status' => true,
                    'message' => 'ok',
                    'is_successful' => true,
                    'data' => [
                        'id' => Str::random(10),
                        'status' => 'success',
                        'amount' => $payment?->amount ?? 0,
                        'currency' => $payment?->currency ?? 'NGN',
                        'metadata' => [
                            'user_id' => $payment?->user_id,
                            'package_id' => $payment?->word_package_id,
                        ],
                        'authorization' => [
                            'card_type' => 'visa',
                            'last4' => '1111',
                            'bank' => 'Test Bank',
                        ],
                        'paid_at' => now()->toIso8601String(),
                    ],
                ];
            }
        });
    }

    public function test_payment_initialize_callback_and_verify_flow(): void
    {
        $user = User::factory()->create();
        $package = WordPackage::create([
            'name' => 'Test Pack',
            'slug' => 'test-pack',
            'type' => WordPackage::TYPE_PROJECT,
            'tier' => null,
            'words' => 5000,
            'price' => 150000, // in kobo
            'currency' => 'NGN',
            'description' => 'Test package',
            'features' => [],
            'sort_order' => 1,
            'is_active' => true,
            'is_popular' => false,
        ]);

        // Initialize
        $response = $this->actingAs($user)->postJson(route('payments.initialize'), [
            'package_id' => $package->id,
        ]);

        $response->assertStatus(200)->assertJson([
            'success' => true,
            'data' => [
                'access_code' => $response['data']['access_code'],
            ],
        ]);

        $payment = Payment::first();
        $this->assertNotNull($payment);
        $this->assertEquals(Payment::STATUS_PENDING, $payment->status);
        $this->assertEquals($package->price, $payment->amount);

        // Verify (simulates inline flow)
        $verify = $this->actingAs($user)->postJson(route('payments.verify'), [
            'reference' => $payment->paystack_reference,
        ]);

        $verify->assertStatus(200)->assertJson([
            'success' => true,
            'data' => [
                'words_credited' => $package->words,
            ],
        ]);

        $payment->refresh();
        $user->refresh();

        $this->assertEquals(Payment::STATUS_SUCCESS, $payment->status);
        $this->assertEquals($package->words, $payment->words_purchased);
        $this->assertEquals($package->words, $user->word_balance);

        // Callback (simulates redirect flow) on a fresh pending payment
        $payment2 = Payment::createPending($user, $package, Payment::generateReference());

        $callback = $this->actingAs($user)->get(route('payments.callback', [
            'reference' => $payment2->paystack_reference,
        ]));

        $callback->assertRedirect(route('pricing'));

        $payment2->refresh();
        $this->assertEquals(Payment::STATUS_SUCCESS, $payment2->status);
    }

    public function test_payment_initialize_requires_authentication(): void
    {
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

        $response = $this->postJson(route('payments.initialize'), [
            'package_id' => $package->id,
        ]);

        $response->assertStatus(401);
    }

    public function test_payment_initialize_rejects_invalid_package(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('payments.initialize'), [
            'package_id' => 99999,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['package_id']);
    }

    public function test_payment_initialize_rejects_inactive_package(): void
    {
        $user = User::factory()->create();
        $package = WordPackage::create([
            'name' => 'Inactive Pack',
            'slug' => 'inactive-pack',
            'type' => WordPackage::TYPE_PROJECT,
            'tier' => null,
            'words' => 5000,
            'price' => 150000,
            'currency' => 'NGN',
            'description' => 'Test package',
            'features' => [],
            'sort_order' => 1,
            'is_active' => false,
            'is_popular' => false,
        ]);

        $response = $this->actingAs($user)->postJson(route('payments.initialize'), [
            'package_id' => $package->id,
        ]);

        $response->assertStatus(400)->assertJson([
            'success' => false,
            'message' => 'This package is no longer available',
        ]);
    }

    public function test_payment_verify_is_idempotent(): void
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

        // Initialize payment
        $response = $this->actingAs($user)->postJson(route('payments.initialize'), [
            'package_id' => $package->id,
        ]);

        $payment = Payment::first();

        // Verify first time
        $verify1 = $this->actingAs($user)->postJson(route('payments.verify'), [
            'reference' => $payment->paystack_reference,
        ]);

        $verify1->assertStatus(200)->assertJson([
            'success' => true,
            'data' => [
                'words_credited' => $package->words,
            ],
        ]);

        $user->refresh();
        $initialBalance = $user->word_balance;

        // Verify second time - should not double-credit
        $verify2 = $this->actingAs($user)->postJson(route('payments.verify'), [
            'reference' => $payment->paystack_reference,
        ]);

        $verify2->assertStatus(200)->assertJson([
            'success' => true,
            'message' => 'Payment already processed',
        ]);

        $user->refresh();
        $this->assertEquals($initialBalance, $user->word_balance);

        // Verify third time - still idempotent
        $verify3 = $this->actingAs($user)->postJson(route('payments.verify'), [
            'reference' => $payment->paystack_reference,
        ]);

        $verify3->assertStatus(200);
        $user->refresh();
        $this->assertEquals($initialBalance, $user->word_balance);
    }

    public function test_payment_verify_rejects_nonexistent_reference(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('payments.verify'), [
            'reference' => 'NONEXISTENT_REF',
        ]);

        $response->assertStatus(404)->assertJson([
            'success' => false,
            'message' => 'Payment not found',
        ]);
    }

    public function test_payment_callback_handles_missing_reference(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('payments.callback', [
            'reference' => 'INVALID_REF',
        ]));

        $response->assertRedirect(route('pricing'))
            ->assertSessionHas('error', 'Payment not found');
    }

    public function test_payment_creates_word_transaction(): void
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

        // Initialize and verify payment
        $initResponse = $this->actingAs($user)->postJson(route('payments.initialize'), [
            'package_id' => $package->id,
        ]);

        $payment = Payment::first();

        $this->actingAs($user)->postJson(route('payments.verify'), [
            'reference' => $payment->paystack_reference,
        ]);

        // Check word transaction was created
        $this->assertDatabaseHas('word_transactions', [
            'user_id' => $user->id,
            'type' => 'purchase',
            'words' => $package->words,
            'reference_type' => 'payment',
            'reference_id' => $payment->id,
        ]);
    }

    public function test_payment_updates_user_balances(): void
    {
        $user = User::factory()->create([
            'word_balance' => 1000,
            'total_words_purchased' => 1000,
        ]);

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

        // Initialize and verify
        $this->actingAs($user)->postJson(route('payments.initialize'), [
            'package_id' => $package->id,
        ]);

        $payment = Payment::first();

        $this->actingAs($user)->postJson(route('payments.verify'), [
            'reference' => $payment->paystack_reference,
        ]);

        $user->refresh();

        $this->assertEquals(6000, $user->word_balance);
        $this->assertEquals(6000, $user->total_words_purchased);
    }
}

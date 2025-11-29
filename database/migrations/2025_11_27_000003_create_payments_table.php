<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('word_package_id')->nullable()->constrained()->nullOnDelete();

            // Payment details
            $table->unsignedInteger('amount'); // Amount in kobo
            $table->string('currency', 3)->default('NGN');
            $table->unsignedInteger('words_purchased'); // Words to be credited

            // Paystack fields
            $table->string('paystack_reference')->unique(); // Our generated reference
            $table->string('paystack_access_code')->nullable(); // From initialize
            $table->string('paystack_transaction_id')->nullable(); // From verify

            // Status tracking
            $table->enum('status', [
                'pending',      // Payment initialized
                'success',      // Payment verified and words credited
                'failed',       // Payment failed
                'abandoned',    // User didn't complete
                'refunded',     // Payment was refunded
            ])->default('pending');

            // Payment method info
            $table->string('channel')->nullable(); // card, bank, ussd, bank_transfer
            $table->string('card_type')->nullable(); // visa, mastercard
            $table->string('card_last4')->nullable(); // Last 4 digits
            $table->string('bank')->nullable(); // Bank name for bank payments

            // Timestamps
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('verified_at')->nullable();

            // Paystack response data
            $table->json('paystack_response')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['user_id', 'status']);
            $table->index('paystack_reference');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};

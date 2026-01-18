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
        Schema::create('referral_earnings', function (Blueprint $table) {
            $table->id();

            // The referrer who earned this commission
            $table->foreignId('referrer_id')->constrained('users')->cascadeOnDelete();

            // The referred user who made the payment
            $table->foreignId('referee_id')->constrained('users')->cascadeOnDelete();

            // The payment that triggered this commission
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();

            // Commission details (amounts in kobo)
            $table->unsignedInteger('payment_amount');
            $table->unsignedInteger('commission_amount');
            $table->decimal('commission_rate', 5, 2);

            // Status tracking
            $table->enum('status', ['pending', 'paid', 'failed', 'refunded'])->default('pending');

            // Paystack split details
            $table->string('paystack_split_code')->nullable();
            $table->json('paystack_split_response')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['referrer_id', 'status']);
            $table->index(['referee_id', 'created_at']);
            $table->index('payment_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('referral_earnings');
    }
};

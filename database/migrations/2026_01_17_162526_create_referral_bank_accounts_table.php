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
        Schema::create('referral_bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Bank details
            $table->string('bank_code');
            $table->string('bank_name');
            $table->string('account_number', 10);
            $table->string('account_name');

            // Paystack subaccount
            $table->string('subaccount_code')->unique();

            // Verification status
            $table->boolean('is_verified')->default(false);
            $table->timestamp('verified_at')->nullable();

            // Active status (user may change bank)
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            // Indexes
            $table->index(['user_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('referral_bank_accounts');
    }
};

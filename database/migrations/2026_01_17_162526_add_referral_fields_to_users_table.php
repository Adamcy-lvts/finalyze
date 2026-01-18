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
        Schema::table('users', function (Blueprint $table) {
            // Unique referral code for this user (e.g., "AB1X2Y3Z")
            $table->string('referral_code', 10)->unique()->nullable()->after('received_signup_bonus');

            // Who referred this user (nullable - not everyone is referred)
            $table->foreignId('referred_by')->nullable()->after('referral_code')
                ->constrained('users')->nullOnDelete();

            // Custom commission rate for this user (null = use default from settings)
            $table->decimal('referral_commission_rate', 5, 2)->nullable()->after('referred_by');

            // Paystack subaccount code (set when user adds bank details)
            $table->string('paystack_subaccount_code')->nullable()->after('referral_commission_rate');

            // Bank account setup status for referral payouts
            $table->boolean('referral_bank_setup_complete')->default(false)->after('paystack_subaccount_code');

            // When this user was referred
            $table->timestamp('referred_at')->nullable()->after('referral_bank_setup_complete');

            // Indexes
            $table->index('referral_code');
            $table->index('referred_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['referred_by']);
            $table->dropIndex(['referral_code']);
            $table->dropIndex(['referred_by']);
            $table->dropColumn([
                'referral_code',
                'referred_by',
                'referral_commission_rate',
                'paystack_subaccount_code',
                'referral_bank_setup_complete',
                'referred_at',
            ]);
        });
    }
};

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
            $table->enum('affiliate_status', ['pending', 'approved', 'rejected'])->nullable()->after('referred_at');
            $table->timestamp('affiliate_requested_at')->nullable()->after('affiliate_status');
            $table->timestamp('affiliate_approved_at')->nullable()->after('affiliate_requested_at');
            $table->text('affiliate_notes')->nullable()->after('affiliate_approved_at');
            $table->boolean('affiliate_is_pure')->default(false)->after('affiliate_notes');
            $table->timestamp('affiliate_promo_dismissed_at')->nullable()->after('affiliate_is_pure');

            $table->index('affiliate_status');
            $table->index('affiliate_requested_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['affiliate_status']);
            $table->dropIndex(['affiliate_requested_at']);
            $table->dropColumn([
                'affiliate_status',
                'affiliate_requested_at',
                'affiliate_approved_at',
                'affiliate_notes',
                'affiliate_is_pure',
                'affiliate_promo_dismissed_at',
            ]);
        });
    }
};

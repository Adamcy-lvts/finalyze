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
            $table->boolean('is_banned')->default(false)->after('received_signup_bonus');
            $table->timestamp('banned_at')->nullable()->after('is_banned');
            $table->text('ban_reason')->nullable()->after('banned_at');
            $table->foreignId('banned_by')->nullable()->after('ban_reason')->constrained('users')->nullOnDelete();
            $table->timestamp('last_active_at')->nullable()->after('banned_by');
            $table->softDeletes();

            $table->index('is_banned', 'users_idx_is_banned');
            $table->index('last_active_at', 'users_idx_last_active');
            $table->index('deleted_at', 'users_idx_deleted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['users_idx_is_banned', 'users_idx_last_active', 'users_idx_deleted_at']);
            $table->dropForeign(['banned_by']);
            $table->dropColumn(['is_banned', 'banned_at', 'ban_reason', 'banned_by', 'last_active_at', 'deleted_at']);
        });
    }
};

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
        Schema::create('admin_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained('users')->cascadeOnDelete();
            $table->string('action', 50);
            $table->text('description');
            $table->string('model_type', 100)->nullable();
            $table->unsignedBigInteger('model_id')->nullable();
            $table->json('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index('admin_id', 'admin_audit_logs_idx_admin_id');
            $table->index(['model_type', 'model_id'], 'admin_audit_logs_idx_model');
            $table->index('created_at', 'admin_audit_logs_idx_created_at');
        });

        Schema::create('feature_flags', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('key', 'feature_flags_idx_key');
            $table->index('is_enabled', 'feature_flags_idx_enabled');
        });

        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->json('value');
            $table->enum('type', ['string', 'integer', 'boolean', 'json'])->default('string');
            $table->string('group', 50)->default('general');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index('key', 'system_settings_idx_key');
            $table->index('group', 'system_settings_idx_group');
        });

        Schema::create('admin_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('type', 100);
            $table->string('title');
            $table->text('message');
            $table->enum('severity', ['info', 'warning', 'error', 'critical'])->default('info');
            $table->json('data')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index('is_read', 'admin_notifications_idx_is_read');
            $table->index('created_at', 'admin_notifications_idx_created_at');
            $table->index('severity', 'admin_notifications_idx_severity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_notifications');
        Schema::dropIfExists('system_settings');
        Schema::dropIfExists('feature_flags');
        Schema::dropIfExists('admin_audit_logs');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registration_invites', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('uses')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('registration_invite_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invite_id')->constrained('registration_invites')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('email')->nullable();
            $table->string('ip', 64)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('redeemed_at');
            $table->timestamps();

            $table->unique(['invite_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registration_invite_redemptions');
        Schema::dropIfExists('registration_invites');
    }
};


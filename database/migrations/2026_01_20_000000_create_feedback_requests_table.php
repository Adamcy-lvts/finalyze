<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feedback_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->nullableMorphs('requestable');
            $table->string('source', 64);
            $table->string('status', 32)->default('eligible');
            $table->unsignedTinyInteger('rating')->nullable();
            $table->text('comment')->nullable();
            $table->json('context')->nullable();
            $table->timestamp('shown_at')->nullable();
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('cooldown_until')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status', 'created_at']);
            $table->index(['source', 'created_at']);
            $table->index(['cooldown_until']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback_requests');
    }
};

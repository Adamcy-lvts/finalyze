<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('defense_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('defense_sessions')->cascadeOnDelete();
            $table->enum('role', ['panelist', 'student', 'system']);
            $table->string('panelist_persona', 50)->nullable();
            $table->text('content');
            $table->string('audio_url', 500)->nullable();
            $table->decimal('audio_duration_seconds', 8, 2)->nullable();
            $table->unsignedInteger('tokens_used')->default(0);
            $table->unsignedInteger('response_time_ms')->nullable();
            $table->json('ai_feedback')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('defense_messages');
    }
};

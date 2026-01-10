<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('defense_sessions')) {
            return;
        }

        Schema::create('defense_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->enum('mode', ['text', 'audio'])->default('text');
            $table->enum('status', ['pending', 'in_progress', 'completed', 'abandoned'])->default('pending');
            $table->json('selected_panelists');
            $table->enum('difficulty_level', ['undergraduate', 'masters', 'doctoral'])->default('undergraduate');
            $table->unsignedInteger('time_limit_minutes')->nullable();
            $table->unsignedInteger('question_limit')->nullable();
            $table->unsignedInteger('session_duration_seconds')->default(0);
            $table->unsignedInteger('questions_asked')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('performance_metrics')->nullable();
            $table->unsignedInteger('readiness_score')->default(0);
            $table->unsignedInteger('words_consumed')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('defense_sessions');
    }
};

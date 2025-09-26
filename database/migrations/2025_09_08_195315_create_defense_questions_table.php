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
        Schema::create('defense_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('project_id')->constrained()->onDelete('cascade');
            $table->foreignId('chapter_id')->nullable()->constrained()->onDelete('cascade');
            $table->integer('chapter_number')->nullable();
            $table->text('question');
            $table->text('suggested_answer');
            $table->json('key_points')->nullable();
            $table->enum('difficulty', ['easy', 'medium', 'hard'])->default('medium');
            $table->string('category')->default('general');
            $table->string('ai_model')->nullable();
            $table->integer('generation_batch')->default(1);
            $table->boolean('is_active')->default(true);
            $table->integer('times_viewed')->default(0);
            $table->boolean('user_marked_helpful')->nullable();
            $table->timestamp('last_shown_at')->nullable();
            $table->timestamps();

            // Indexes for better performance
            $table->index(['project_id', 'is_active']);
            $table->index(['project_id', 'chapter_number']);
            $table->index(['user_id', 'project_id']);
            $table->index('last_shown_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('defense_questions');
    }
};

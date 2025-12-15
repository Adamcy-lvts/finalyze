<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chapter_progress_guidances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('project_id')->constrained()->onDelete('cascade');
            $table->foreignId('chapter_id')->constrained()->onDelete('cascade');
            $table->unsignedInteger('chapter_number');

            $table->string('fingerprint', 64);
            $table->string('stage', 50);
            $table->string('stage_label', 100);
            $table->unsignedTinyInteger('completion_percentage');

            $table->text('contextual_tip')->nullable();
            $table->json('next_steps');
            $table->json('writing_milestones');
            $table->json('completed_step_ids')->nullable();
            $table->json('meta')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'chapter_id']);
            $table->index(['project_id', 'chapter_number']);
            $table->unique(['user_id', 'chapter_id', 'fingerprint']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chapter_progress_guidances');
    }
};


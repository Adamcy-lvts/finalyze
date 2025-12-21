<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('defense_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('defense_sessions')->cascadeOnDelete();
            $table->unsignedInteger('overall_score')->nullable();
            $table->json('strengths')->nullable();
            $table->json('weaknesses')->nullable();
            $table->json('question_performance')->nullable();
            $table->text('recommendations')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('defense_feedback');
    }
};

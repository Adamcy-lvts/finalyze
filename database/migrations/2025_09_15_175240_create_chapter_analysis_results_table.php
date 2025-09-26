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
        Schema::create('chapter_analysis_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chapter_id')->constrained()->onDelete('cascade');

            // 100-point scoring system breakdown
            $table->decimal('grammar_style_score', 4, 1)->default(0); // 20 points max
            $table->decimal('readability_score', 4, 1)->default(0);    // 15 points max
            $table->decimal('structure_score', 4, 1)->default(0);      // 15 points max
            $table->decimal('citations_score', 4, 1)->default(0);      // 20 points max
            $table->decimal('originality_score', 4, 1)->default(0);    // 20 points max
            $table->decimal('argument_score', 4, 1)->default(0);       // 10 points max
            $table->decimal('total_score', 5, 1)->default(0);          // 100 points total

            // Detailed metrics
            $table->integer('word_count');
            $table->integer('character_count');
            $table->integer('paragraph_count');
            $table->integer('sentence_count');
            $table->integer('citation_count');
            $table->integer('verified_citation_count');

            // Quality indicators
            $table->decimal('completion_percentage', 5, 2)->default(0);
            $table->decimal('reading_time_minutes', 6, 1)->default(0);
            $table->boolean('meets_defense_requirement')->default(false);
            $table->boolean('meets_completion_threshold')->default(false); // 80% threshold

            // Analysis metadata
            $table->json('grammar_issues')->nullable();     // Detailed grammar problems
            $table->json('readability_metrics')->nullable(); // Flesch-Kincaid, etc.
            $table->json('structure_feedback')->nullable();  // Organization issues
            $table->json('citation_analysis')->nullable();   // Citation quality details
            $table->json('suggestions')->nullable();         // Improvement recommendations

            $table->timestamp('analyzed_at');
            $table->timestamps();

            // Indexes for performance
            $table->index('chapter_id');
            $table->index('total_score');
            $table->index('meets_completion_threshold');
            $table->index('analyzed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chapter_analysis_results');
    }
};

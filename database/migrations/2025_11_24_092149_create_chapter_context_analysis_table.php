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
        Schema::create('chapter_context_analysis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->onDelete('cascade');
            $table->foreignId('chapter_id')->constrained()->onDelete('cascade');
            $table->integer('word_count')->default(0);
            $table->integer('citation_count')->default(0);
            $table->integer('table_count')->default(0);
            $table->integer('figure_count')->default(0);
            $table->integer('claim_count')->default(0);
            $table->boolean('has_introduction')->default(false);
            $table->boolean('has_conclusion')->default(false);
            $table->json('detected_issues')->nullable();
            $table->json('content_quality_metrics')->nullable();
            $table->timestamp('last_analyzed_at');
            $table->timestamps();

            $table->unique('chapter_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chapter_context_analysis');
    }
};

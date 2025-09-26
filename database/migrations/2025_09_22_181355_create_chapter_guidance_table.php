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
        Schema::create('chapter_guidance', function (Blueprint $table) {
            $table->id();
            $table->string('course');
            $table->string('faculty');
            $table->string('field_of_study');
            $table->string('academic_level'); // undergraduate, postgraduate, etc.
            $table->integer('chapter_number');
            $table->string('chapter_title');
            $table->text('writing_guidance'); // AI-generated guidance
            $table->json('key_elements'); // Important elements for this chapter
            $table->json('requirements'); // Data/design/stats requirements
            $table->json('tips'); // Writing tips
            $table->text('methodology_guidance')->nullable(); // For methodology chapters
            $table->text('data_guidance')->nullable(); // For data-heavy chapters
            $table->text('analysis_guidance')->nullable(); // For analysis chapters
            $table->integer('usage_count')->default(0); // Track reuse
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            // Indexes for fast lookups (separate to avoid key length issues)
            $table->index(['course', 'faculty', 'chapter_number']);
            $table->index(['field_of_study', 'academic_level']);
            $table->index('chapter_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chapter_guidance');
    }
};

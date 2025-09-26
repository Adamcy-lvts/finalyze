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
        Schema::create('faculty_structures', function (Blueprint $table) {
            $table->id();
            $table->string('faculty_name')->unique();
            $table->string('faculty_slug')->unique();
            $table->text('description')->nullable();
            $table->json('academic_levels'); // ['undergraduate', 'masters', 'phd']
            $table->json('default_structure'); // Chapter structure, timelines, requirements
            $table->json('chapter_templates'); // Template definitions for each chapter type
            $table->json('guidance_templates'); // Guidance checklists and templates
            $table->json('terminology'); // Faculty-specific terminology and definitions
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            // Indexes for performance
            $table->index(['faculty_name', 'is_active']);
            $table->index('faculty_slug');
            $table->index('sort_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('faculty_structures');
    }
};

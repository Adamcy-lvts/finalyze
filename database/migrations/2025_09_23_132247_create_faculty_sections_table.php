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
        Schema::create('faculty_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('faculty_chapter_id')->constrained('faculty_chapters')->cascadeOnDelete();
            $table->string('section_number'); // e.g., "1.1", "1.2", etc.
            $table->string('section_title');
            $table->text('description')->nullable();
            $table->text('writing_guidance')->nullable();
            $table->json('tips')->nullable(); // Array of tips
            $table->integer('target_word_count')->default(500);
            $table->boolean('is_required')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            // Indexes
            $table->index(['faculty_chapter_id', 'sort_order']);
            $table->index('section_number');

            // Ensure unique section numbers per chapter
            $table->unique(['faculty_chapter_id', 'section_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('faculty_sections');
    }
};

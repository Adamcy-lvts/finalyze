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
        Schema::create('faculty_chapters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('faculty_structure_id')->constrained()->cascadeOnDelete();
            $table->string('academic_level'); // undergraduate, masters, phd
            $table->string('project_type')->default('thesis'); // thesis, project, dissertation
            $table->integer('chapter_number');
            $table->string('chapter_title');
            $table->text('description')->nullable();
            $table->integer('target_word_count')->default(3000);
            $table->integer('completion_threshold')->default(80); // percentage
            $table->boolean('is_required')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            // Indexes with shorter names
            $table->index(['faculty_structure_id', 'academic_level', 'project_type'], 'faculty_chapter_lookup_idx');
            $table->index(['chapter_number', 'academic_level'], 'faculty_chapter_num_level_idx');

            // Ensure unique chapter numbers per faculty/level/type
            $table->unique(['faculty_structure_id', 'academic_level', 'project_type', 'chapter_number'], 'faculty_chapter_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('faculty_chapters');
    }
};

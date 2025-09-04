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
        Schema::create('project_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g., "Final Year Project", "Seminar"
            $table->string('slug')->unique();
            $table->json('academic_levels'); // ["undergraduate", "postgraduate", etc.]
            $table->text('description');
            $table->integer('default_chapter_count')->default(5);
            $table->json('chapter_structure'); // Chapter templates
            $table->integer('target_word_count')->nullable();
            $table->string('target_duration')->nullable(); // e.g., "2 semesters"
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index('slug');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_categories');
    }
};

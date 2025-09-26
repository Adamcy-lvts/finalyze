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
        Schema::create('chapter_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_outline_id')->constrained()->cascadeOnDelete();
            $table->string('section_number'); // e.g., "1.1", "1.2", "1.1.1"
            $table->string('section_title');
            $table->text('section_description')->nullable();
            $table->integer('target_word_count')->default(500);
            $table->integer('display_order')->default(0);
            $table->boolean('is_required')->default(true);
            $table->boolean('is_completed')->default(false);
            $table->integer('current_word_count')->default(0);
            $table->timestamps();

            $table->index(['project_outline_id', 'display_order']);
            $table->unique(['project_outline_id', 'section_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chapter_sections');
    }
};

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
        Schema::create('project_chapter_guidance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->onDelete('cascade');
            $table->foreignId('chapter_guidance_id')->constrained('chapter_guidance')->onDelete('cascade');
            $table->integer('chapter_number');
            $table->text('project_specific_notes')->nullable(); // Custom notes for this project
            $table->json('custom_elements')->nullable(); // Project-specific additional elements
            $table->boolean('is_completed')->default(false);
            $table->timestamp('accessed_at')->nullable();
            $table->timestamps();

            // Unique constraint: one guidance per project per chapter
            $table->unique(['project_id', 'chapter_number']);

            // Index for fast lookups
            $table->index(['project_id', 'chapter_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_chapter_guidance');
    }
};

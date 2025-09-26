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
        Schema::create('project_outlines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->integer('chapter_number');
            $table->string('chapter_title');
            $table->integer('target_word_count')->default(2500);
            $table->integer('completion_threshold')->default(80); // Percentage
            $table->text('description')->nullable();
            $table->integer('display_order')->default(0);
            $table->boolean('is_required')->default(true);
            $table->timestamps();

            $table->unique(['project_id', 'chapter_number']);
            $table->index(['project_id', 'display_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_outlines');
    }
};

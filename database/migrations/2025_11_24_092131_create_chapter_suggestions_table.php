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
        Schema::create('chapter_suggestions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->onDelete('cascade');
            $table->foreignId('project_category_id')->constrained()->onDelete('cascade');
            $table->integer('chapter_number');
            $table->string('course_field')->nullable();
            $table->text('topic_keywords');
            $table->string('suggestion_type'); // 'writing_guide', 'structure', 'citation', 'data', 'argument'
            $table->text('suggestion_content');
            $table->json('metadata')->nullable();
            $table->integer('usage_count')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index(['project_category_id', 'chapter_number']);
            $table->index(['course_field', 'chapter_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chapter_suggestions');
    }
};

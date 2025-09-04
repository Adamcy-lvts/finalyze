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
        Schema::create('project_metadata', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->onDelete('cascade');
            $table->string('academic_session')->nullable(); // e.g., "2024/2025"
            $table->string('matriculation_number')->nullable();
            $table->string('department')->nullable();
            $table->string('faculty')->nullable();
            $table->date('expected_completion_date')->nullable();
            $table->json('chapter_templates')->nullable(); // University-specific templates
            $table->json('formatting_rules')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_metadata');
    }
};

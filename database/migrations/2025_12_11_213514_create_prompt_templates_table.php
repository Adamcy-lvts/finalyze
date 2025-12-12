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
        Schema::create('prompt_templates', function (Blueprint $table) {
            $table->id();

            // Context matching fields
            $table->enum('context_type', ['faculty', 'department', 'course', 'field_of_study', 'topic_keyword']);
            $table->string('context_value');
            $table->foreignId('parent_template_id')->nullable()->constrained('prompt_templates')->nullOnDelete();
            $table->string('chapter_type', 50)->nullable(); // introduction, methodology, results, etc.

            // Content requirements (JSON)
            $table->json('table_requirements')->nullable();
            $table->json('diagram_requirements')->nullable();
            $table->json('calculation_requirements')->nullable();
            $table->json('code_requirements')->nullable();
            $table->json('placeholder_rules')->nullable();

            // Tool recommendations
            $table->json('recommended_tools')->nullable();

            // The actual prompt templates
            $table->text('system_prompt')->nullable();
            $table->text('chapter_prompt_template')->nullable();

            // Additional context
            $table->json('mock_data_config')->nullable();
            $table->json('citation_requirements')->nullable();
            $table->json('formatting_rules')->nullable();

            // Metadata
            $table->integer('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Indexes for faster lookups
            $table->index(['context_type', 'context_value']);
            $table->index(['chapter_type']);
            $table->index(['is_active', 'priority']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prompt_templates');
    }
};

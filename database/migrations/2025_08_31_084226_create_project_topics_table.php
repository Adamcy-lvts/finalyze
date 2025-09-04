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
        Schema::create('project_topics', function (Blueprint $table) {
            $table->id();
            $table->string('field_of_study')->nullable(); // Allow null for students needing guidance
            $table->string('faculty');
            $table->string('department');
            $table->string('course');
            $table->string('university');
            $table->enum('academic_level', ['undergraduate', 'postgraduate', 'hnd', 'nd']);
            $table->string('title');
            $table->text('description');
            $table->string('difficulty');
            $table->string('timeline');
            $table->string('resource_level');
            $table->integer('feasibility_score');
            $table->json('keywords');
            $table->string('research_type');
            $table->integer('selection_count')->default(0);
            $table->timestamp('last_selected_at')->nullable();
            $table->timestamps();

            // Indexes for efficient querying (focused on academic context matching)
            $table->index(['faculty', 'department', 'academic_level']);
            $table->index(['faculty', 'academic_level']);
            $table->index(['department', 'academic_level']);
            $table->index(['course', 'academic_level']);
            $table->index(['field_of_study', 'academic_level']);
            $table->index('selection_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_topics');
    }
};

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
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('project_category_id')->nullable()->constrained()->onDelete('set null');
            $table->string('title')->nullable();
            $table->string('topic')->nullable();
            $table->string('slug')->unique();
            $table->enum('type', ['undergraduate', 'postgraduate', 'hnd', 'nd']);
            $table->enum('status', ['setup', 'topic_selection', 'topic_pending_approval', 'topic_approved', 'writing', 'completed'])->default('setup');
            $table->integer('setup_step')->default(1); // Track which step of setup wizard user is on
            $table->json('setup_data')->nullable(); // Store partial wizard data
            $table->enum('mode', ['auto', 'manual'])->nullable();
            $table->string('field_of_study');
            $table->string('university');
            $table->string('course');
            $table->string('supervisor_name')->nullable();
            $table->integer('current_chapter')->default(0);
            $table->boolean('is_active')->default(true);
            $table->json('settings')->nullable(); // Store project-specific settings
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};

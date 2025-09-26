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
        Schema::create('chat_file_uploads', function (Blueprint $table) {
            $table->id();
            $table->uuid('upload_id')->unique();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('project_id')->constrained()->onDelete('cascade');
            $table->integer('chapter_number');
            $table->string('session_id');

            // File information
            $table->string('original_filename');
            $table->string('stored_path');
            $table->string('mime_type');
            $table->integer('file_size');

            // Analysis results
            $table->longText('extracted_text');
            $table->json('analysis_results');

            // Metadata
            $table->integer('word_count')->default(0);
            $table->integer('citations_found')->default(0);
            $table->json('main_topics')->nullable();

            // Status and usage
            $table->enum('status', ['processing', 'completed', 'failed'])->default('processing');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_accessed_at')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['user_id', 'project_id', 'chapter_number']);
            $table->index(['session_id', 'is_active']);
            $table->index('upload_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_file_uploads');
    }
};

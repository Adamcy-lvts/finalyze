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
        Schema::create('chat_conversations', function (Blueprint $table) {
            $table->id();

            // Foreign Keys
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('chapter_number'); // Chapter number (not FK since chapters are created dynamically)

            // Session Management
            $table->string('session_id', 36); // UUID for grouping related messages in a chat session
            $table->integer('message_order')->default(0); // Order of messages within a session

            // Message Data
            $table->enum('message_type', ['user', 'ai', 'system'])->index();
            $table->text('content');
            $table->json('context_data')->nullable(); // Store selected text, chapter content snippets, etc.

            // Metadata
            $table->string('ai_model')->nullable(); // Track which AI model generated the response
            $table->integer('tokens_used')->nullable(); // For cost tracking
            $table->decimal('response_time', 8, 3)->nullable(); // Response time in seconds

            // Indexes for performance
            $table->index(['user_id', 'project_id', 'chapter_number']);
            $table->index(['session_id', 'message_order']);
            $table->index('created_at');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_conversations');
    }
};

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
        Schema::create('user_topic_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('project_id')->constrained()->onDelete('cascade');
            $table->string('academic_context_hash'); // Hash of course+university+academic_level for efficient querying
            $table->json('request_metadata')->nullable(); // Store additional context if needed
            $table->timestamps();

            // Indexes for efficient queries
            $table->index(['user_id', 'project_id'], 'user_topic_requests_user_project_idx');
            $table->index(['user_id', 'academic_context_hash', 'created_at'], 'user_topic_requests_context_time_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_topic_requests');
    }
};

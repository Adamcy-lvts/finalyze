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
        Schema::create('citation_verifications', function (Blueprint $table) {
            $table->id();
            $table->string('raw_citation', 1000);
            $table->string('detected_format')->nullable();

            // Verification attempts
            $table->json('api_responses')->nullable(); // Store all API responses
            $table->enum('status', ['pending', 'processing', 'verified', 'failed', 'timeout']);
            $table->integer('attempts')->default(0);

            // Results
            $table->foreignId('matched_citation_id')->nullable()->constrained('citations');
            $table->decimal('match_confidence', 3, 2)->nullable();
            $table->json('discrepancies')->nullable(); // Differences found

            // Performance tracking
            $table->integer('verification_time_ms')->nullable();
            $table->string('session_id')->nullable()->index(); // For AI generation tracking

            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('citation_verifications');
    }
};

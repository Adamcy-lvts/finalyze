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
        Schema::create('chapter_citation_detections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chapter_id')->constrained()->onDelete('cascade');
            $table->json('claims'); // Array of detected claims with suggestions
            $table->integer('total_claims')->default(0);
            $table->integer('words_used')->default(0); // AI tokens used
            $table->timestamp('detected_at')->useCurrent();
            $table->timestamps();

            // Index for fast lookup
            $table->index(['chapter_id', 'detected_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chapter_citation_detections');
    }
};

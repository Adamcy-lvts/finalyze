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
        Schema::create('document_citations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('chapter_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('citation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Citation context
            $table->text('inline_text'); // The actual text used in document
            $table->string('format_style')->default('apa'); // Citation style used
            $table->integer('position')->nullable(); // Position in text

            // Source tracking
            $table->enum('source', ['manual', 'ai_generated', 'suggested', 'imported']);
            $table->boolean('user_approved')->default(false);
            $table->boolean('needs_review')->default(false);

            // For unverified citations
            $table->text('raw_citation')->nullable(); // Original unverified text
            $table->json('placeholder_data')->nullable(); // Temporary data for placeholders

            $table->timestamps();
            $table->index(['document_id', 'chapter_id', 'source']);
            $table->index(['needs_review', 'user_approved']);

            // Additional indexes for frequent queries
            $table->index(['citation_id', 'user_approved'], 'doc_citations_citation_approved_idx');
            $table->index(['chapter_id', 'needs_review'], 'doc_citations_chapter_review_idx');
            $table->index(['source', 'needs_review'], 'doc_citations_source_review_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_citations');
    }
};

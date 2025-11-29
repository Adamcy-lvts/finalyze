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
        Schema::create('citations', function (Blueprint $table) {
            $table->id();
            $table->string('citation_key')->unique(); // Unique identifier (DOI, PubMed ID, etc.)
            $table->string('doi')->nullable()->index();
            $table->string('pubmed_id')->nullable()->index();
            $table->string('arxiv_id')->nullable()->index();

            // Core citation data
            $table->json('authors'); // Array of author names
            $table->string('title', 500);
            $table->string('journal')->nullable();
            $table->string('conference')->nullable();
            $table->integer('year')->index();
            $table->string('volume')->nullable();
            $table->string('issue')->nullable();
            $table->string('pages')->nullable();
            $table->string('publisher')->nullable();

            // Verification metadata
            $table->enum('verification_status', ['verified', 'unverified', 'failed', 'manual']);
            $table->decimal('confidence_score', 3, 2)->default(0.00); // 0.00 to 1.00
            $table->json('verification_sources'); // Which APIs verified this
            $table->timestamp('last_verified_at')->nullable();

            // Full citation formats (pre-generated)
            $table->text('apa_format')->nullable();
            $table->text('mla_format')->nullable();
            $table->text('chicago_format')->nullable();
            $table->text('harvard_format')->nullable();

            // Additional metadata
            $table->text('abstract')->nullable();
            $table->json('keywords')->nullable();
            $table->string('url')->nullable();

            $table->timestamps();
            $table->index(['verification_status', 'confidence_score']);

            if (DB::getDriverName() !== 'sqlite') {
                $table->fullText(['title', 'abstract']);
            }

            // Search performance indexes
            $table->index(['doi', 'verification_status'], 'citations_doi_status_idx');
            $table->index(['pubmed_id', 'verification_status'], 'citations_pmid_status_idx');
            $table->index(['arxiv_id', 'verification_status'], 'citations_arxiv_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('citations');
    }
};

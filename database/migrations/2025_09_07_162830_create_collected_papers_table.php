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
        Schema::create('collected_papers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('title', 500);
            $table->string('authors', 500);
            $table->string('content_hash', 64); // MD5 hash of title+authors for uniqueness
            $table->integer('year')->nullable();
            $table->string('venue')->nullable();
            $table->string('doi')->nullable();
            $table->text('url')->nullable();
            $table->text('abstract')->nullable();
            $table->integer('citation_count')->default(0);
            $table->decimal('quality_score', 3, 2)->default(0);
            $table->string('source_api');
            $table->string('paper_id')->nullable();
            $table->boolean('is_open_access')->default(false);
            $table->timestamp('collected_at');
            $table->timestamps();

            // Indexes for performance
            $table->index(['user_id', 'project_id']);
            $table->index(['project_id', 'quality_score']);
            $table->index(['project_id', 'collected_at']);
            $table->unique(['project_id', 'content_hash']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('collected_papers');
    }
};

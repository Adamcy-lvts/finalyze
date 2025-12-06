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
        Schema::create('chapter_analysis_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->onDelete('cascade');
            $table->string('status')->default('queued'); // queued, running, completed, failed, cancelled
            $table->unsignedInteger('total_chapters')->default(0);
            $table->unsignedInteger('completed_chapters')->default(0);
            $table->unsignedInteger('failed_chapters')->default(0);
            $table->unsignedBigInteger('required_words')->default(0);
            $table->unsignedBigInteger('consumed_words')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'status']);
        });

        Schema::create('chapter_analysis_batch_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('chapter_analysis_batches')->onDelete('cascade');
            $table->foreignId('chapter_id')->constrained()->onDelete('cascade');
            $table->foreignId('analysis_result_id')->nullable()->constrained('chapter_analysis_results')->nullOnDelete();
            $table->string('status')->default('queued'); // queued, running, completed, failed, skipped
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['batch_id', 'chapter_id']);
            $table->index(['batch_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chapter_analysis_batch_items');
        Schema::dropIfExists('chapter_analysis_batches');
    }
};

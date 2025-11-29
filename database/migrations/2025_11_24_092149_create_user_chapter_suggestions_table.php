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
        Schema::create('user_chapter_suggestions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('project_id')->constrained()->onDelete('cascade');
            $table->foreignId('chapter_id')->constrained()->onDelete('cascade');
            $table->foreignId('chapter_suggestion_id')->nullable()->constrained()->onDelete('set null');
            $table->string('suggestion_type');
            $table->text('suggestion_content');
            $table->string('trigger_reason'); // Why this suggestion was shown
            $table->json('detected_issues')->nullable(); // Store frontend analysis
            $table->string('status')->default('pending'); // pending, saved, applied, dismissed, auto_dismissed
            $table->timestamp('shown_at');
            $table->timestamp('actioned_at')->nullable();
            $table->timestamps();

            $table->index(['chapter_id', 'status']);
            $table->index(['project_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_chapter_suggestions');
    }
};

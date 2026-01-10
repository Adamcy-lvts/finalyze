<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('defense_slide_decks')) {
            return;
        }

        Schema::create('defense_slide_decks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->json('slides_json')->nullable();
            $table->string('status')->default('queued');
            $table->string('pptx_path')->nullable();
            $table->json('ai_models')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('defense_slide_decks');
    }
};

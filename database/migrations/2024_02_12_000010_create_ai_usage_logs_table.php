<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ai_usage_logs')) {
            Schema::create('ai_usage_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('feature')->nullable();
                $table->string('model')->nullable();
                $table->unsignedInteger('prompt_tokens')->default(0);
                $table->unsignedInteger('completion_tokens')->default(0);
                $table->unsignedInteger('total_tokens')->default(0);
                $table->decimal('cost_usd', 12, 6)->default(0);
                $table->string('request_id')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->index(['created_at', 'model']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_logs');
    }
};

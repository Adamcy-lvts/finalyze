<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ai_usage_daily')) {
            Schema::create('ai_usage_daily', function (Blueprint $table) {
                $table->id();
                $table->date('date');
                $table->string('model')->nullable();
                $table->unsignedBigInteger('prompt_tokens')->default(0);
                $table->unsignedBigInteger('completion_tokens')->default(0);
                $table->unsignedBigInteger('total_tokens')->default(0);
                $table->decimal('cost_usd', 12, 6)->default(0);
                $table->timestamps();
                $table->unique(['date', 'model']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_daily');
    }
};

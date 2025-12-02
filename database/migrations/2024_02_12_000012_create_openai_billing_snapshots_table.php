<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('openai_billing_snapshots')) {
            Schema::create('openai_billing_snapshots', function (Blueprint $table) {
                $table->id();
                $table->decimal('granted_usd', 12, 6)->default(0);
                $table->decimal('used_usd', 12, 6)->default(0);
                $table->decimal('available_usd', 12, 6)->default(0);
                $table->timestamp('expires_at')->nullable();
                $table->date('period_start')->nullable();
                $table->date('period_end')->nullable();
                $table->json('raw')->nullable();
                $table->timestamp('fetched_at');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('openai_billing_snapshots');
    }
};

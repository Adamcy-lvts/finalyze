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
        Schema::create('citation_cache', function (Blueprint $table) {
            $table->id();
            $table->string('cache_key')->unique();
            $table->string('api_source'); // crossref, pubmed, etc.
            $table->json('response_data');
            $table->integer('hits')->default(0);
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['api_source', 'expires_at']);
            $table->index(['expires_at', 'hits'], 'citation_cache_expires_hits_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('citation_cache');
    }
};

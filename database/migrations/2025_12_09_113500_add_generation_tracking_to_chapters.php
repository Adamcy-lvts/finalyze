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
        Schema::table('chapters', function (Blueprint $table) {
            // Track active generation sessions
            $table->boolean('generation_in_progress')->default(false);
            $table->string('generation_id')->nullable();
            $table->timestamp('generation_started_at')->nullable();
            $table->integer('generation_last_saved_words')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chapters', function (Blueprint $table) {
            $table->dropColumn([
                'generation_in_progress',
                'generation_id',
                'generation_started_at',
                'generation_last_saved_words',
            ]);
        });
    }
};

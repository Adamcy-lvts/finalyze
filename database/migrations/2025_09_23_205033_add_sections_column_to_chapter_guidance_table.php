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
        Schema::table('chapter_guidance', function (Blueprint $table) {
            $table->json('sections')->nullable()->after('analysis_guidance');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chapter_guidance', function (Blueprint $table) {
            $table->dropColumn('sections');
        });
    }
};

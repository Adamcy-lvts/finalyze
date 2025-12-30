<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('defense_slide_decks', function (Blueprint $table) {
            $table->json('extraction_data')->nullable()->after('slides_json');
            $table->string('extraction_status')->default('pending')->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('defense_slide_decks', function (Blueprint $table) {
            $table->dropColumn(['extraction_data', 'extraction_status']);
        });
    }
};

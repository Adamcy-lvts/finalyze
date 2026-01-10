<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('defense_slide_decks')) {
            return;
        }

        $addExtractionData = ! Schema::hasColumn('defense_slide_decks', 'extraction_data');
        $addExtractionStatus = ! Schema::hasColumn('defense_slide_decks', 'extraction_status');

        if (! $addExtractionData && ! $addExtractionStatus) {
            return;
        }

        Schema::table('defense_slide_decks', function (Blueprint $table) use ($addExtractionData, $addExtractionStatus) {
            if ($addExtractionData) {
                $table->json('extraction_data')->nullable()->after('slides_json');
            }
            if ($addExtractionStatus) {
                $table->string('extraction_status')->default('pending')->after('status');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('defense_slide_decks')) {
            return;
        }

        $dropColumns = [];
        if (Schema::hasColumn('defense_slide_decks', 'extraction_data')) {
            $dropColumns[] = 'extraction_data';
        }
        if (Schema::hasColumn('defense_slide_decks', 'extraction_status')) {
            $dropColumns[] = 'extraction_status';
        }

        if ($dropColumns === []) {
            return;
        }

        Schema::table('defense_slide_decks', function (Blueprint $table) use ($dropColumns) {
            $table->dropColumn($dropColumns);
        });
    }
};

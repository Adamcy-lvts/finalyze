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
        Schema::table('defense_slide_decks', function (Blueprint $table) {
            $table->boolean('is_wysiwyg')->default(false)->after('slides_json');
            $table->string('editor_version')->nullable()->after('is_wysiwyg');
            $table->json('theme_config')->nullable()->after('editor_version');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('defense_slide_decks', function (Blueprint $table) {
            $table->dropColumn(['is_wysiwyg', 'editor_version', 'theme_config']);
        });
    }
};

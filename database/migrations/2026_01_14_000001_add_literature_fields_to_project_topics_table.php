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
        Schema::table('project_topics', function (Blueprint $table) {
            $table->integer('literature_score')->nullable()->after('feasibility_score');
            $table->integer('literature_count')->nullable()->after('literature_score');
            $table->string('literature_quality', 20)->nullable()->after('literature_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_topics', function (Blueprint $table) {
            $table->dropColumn(['literature_score', 'literature_count', 'literature_quality']);
        });
    }
};

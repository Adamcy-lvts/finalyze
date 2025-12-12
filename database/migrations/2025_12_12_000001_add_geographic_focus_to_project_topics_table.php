<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_topics', function (Blueprint $table) {
            if (! Schema::hasColumn('project_topics', 'geographic_focus')) {
                $table->string('geographic_focus')->default('balanced')->after('academic_level');
                $table->index(['course', 'academic_level', 'university', 'geographic_focus'], 'project_topics_context_geo_idx');
            }
        });

        // Backfill legacy rows (in case they exist with null values)
        if (Schema::hasColumn('project_topics', 'geographic_focus')) {
            DB::table('project_topics')
                ->whereNull('geographic_focus')
                ->update(['geographic_focus' => 'balanced']);
        }
    }

    public function down(): void
    {
        Schema::table('project_topics', function (Blueprint $table) {
            if (Schema::hasColumn('project_topics', 'geographic_focus')) {
                $table->dropIndex('project_topics_context_geo_idx');
                $table->dropColumn('geographic_focus');
            }
        });
    }
};


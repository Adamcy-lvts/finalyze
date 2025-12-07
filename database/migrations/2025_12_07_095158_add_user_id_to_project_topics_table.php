<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('project_topics', function (Blueprint $table) {
            // Table does not have project_id in this environment; place user_id near the top instead.
            $table->foreignId('user_id')
                ->nullable()
                ->after('id')
                ->constrained()
                ->onDelete('cascade');

            $table->index(['user_id', 'academic_level']);
        });

        // Backfill user_id from the owning project when available
        if (Schema::hasColumn('project_topics', 'project_id')) {
            DB::statement("
                UPDATE project_topics pt
                JOIN projects p ON pt.project_id = p.id
                SET pt.user_id = p.user_id
                WHERE pt.user_id IS NULL
            ");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_topics', function (Blueprint $table) {
            if (Schema::hasColumn('project_topics', 'user_id')) {
                $table->dropForeign(['user_id']);
                $table->dropIndex('project_topics_user_id_academic_level_index');
                $table->dropColumn('user_id');
            }
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('project_topics')) {
            return;
        }

        if (Schema::hasColumn('project_topics', 'project_id')) {
            return;
        }

        Schema::table('project_topics', function (Blueprint $table) {
            // Place near user_id if present; otherwise just add it.
            $column = $table->foreignId('project_id')->nullable();
            if (Schema::hasColumn('project_topics', 'user_id')) {
                $column->after('user_id');
            }

            $table->foreign('project_id')->references('id')->on('projects')->nullOnDelete();
            $table->index('project_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('project_topics') || ! Schema::hasColumn('project_topics', 'project_id')) {
            return;
        }

        Schema::table('project_topics', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
            $table->dropIndex(['project_id']);
            $table->dropColumn('project_id');
        });
    }
};


<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add indexes using raw SQL with IF NOT EXISTS logic
        $indexes = [
            'projects_user_id_status_index' => '(user_id, status)',
            'projects_user_id_status_is_active_index' => '(user_id, status, is_active)',
        ];

        foreach ($indexes as $indexName => $columns) {
            $exists = DB::select('SHOW INDEX FROM projects WHERE Key_name = ?', [$indexName]);
            if (empty($exists)) {
                DB::statement("ALTER TABLE projects ADD INDEX {$indexName} {$columns}");
            }
        }

        Schema::table('projects', function (Blueprint $table) {
            // Add last_activity timestamp to track recent projects
            if (! Schema::hasColumn('projects', 'last_activity_at')) {
                $table->timestamp('last_activity_at')->nullable()->after('updated_at');
            }

            // Ensure setup_data is JSON type with proper default
            if (Schema::hasColumn('projects', 'setup_data')) {
                $table->json('setup_data')->nullable()->change();
            }
        });

        // Update existing projects to have proper last_activity_at
        DB::table('projects')->update([
            'last_activity_at' => DB::raw('updated_at'),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop indexes using raw SQL
        $indexes = [
            'projects_user_id_status_index',
            'projects_user_id_status_is_active_index',
        ];

        foreach ($indexes as $indexName) {
            $exists = DB::select('SHOW INDEX FROM projects WHERE Key_name = ?', [$indexName]);
            if (! empty($exists)) {
                DB::statement("DROP INDEX {$indexName} ON projects");
            }
        }

        Schema::table('projects', function (Blueprint $table) {
            if (Schema::hasColumn('projects', 'last_activity_at')) {
                $table->dropColumn('last_activity_at');
            }
        });
    }
};

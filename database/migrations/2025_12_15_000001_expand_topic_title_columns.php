<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE projects MODIFY title TEXT NULL');
            DB::statement('ALTER TABLE projects MODIFY topic TEXT NULL');
            DB::statement('ALTER TABLE project_topics MODIFY title TEXT NOT NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE projects ALTER COLUMN title TYPE TEXT');
            DB::statement('ALTER TABLE projects ALTER COLUMN topic TYPE TEXT');
            DB::statement('ALTER TABLE project_topics ALTER COLUMN title TYPE TEXT');
        } else {
            // For other drivers (e.g., sqlite in tests), rely on schema builder when possible.
            if (Schema::hasTable('projects')) {
                // Some drivers require doctrine/dbal for change(); this is best-effort fallback.
                try {
                    Schema::table('projects', function (Blueprint $table) {
                        $table->text('title')->nullable()->change();
                        $table->text('topic')->nullable()->change();
                    });
                } catch (\Throwable) {
                    // no-op
                }
            }

            if (Schema::hasTable('project_topics')) {
                try {
                    Schema::table('project_topics', function (Blueprint $table) {
                        $table->text('title')->change();
                    });
                } catch (\Throwable) {
                    // no-op
                }
            }
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE projects MODIFY title VARCHAR(255) NULL');
            DB::statement('ALTER TABLE projects MODIFY topic VARCHAR(255) NULL');
            DB::statement('ALTER TABLE project_topics MODIFY title VARCHAR(255) NOT NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE projects ALTER COLUMN title TYPE VARCHAR(255)');
            DB::statement('ALTER TABLE projects ALTER COLUMN topic TYPE VARCHAR(255)');
            DB::statement('ALTER TABLE project_topics ALTER COLUMN title TYPE VARCHAR(255)');
        }
    }
};

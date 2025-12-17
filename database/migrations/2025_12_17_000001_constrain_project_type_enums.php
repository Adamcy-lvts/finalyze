<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        // SQLite doesn't enforce enums in the schema builder.
        if ($driver === 'sqlite') {
            return;
        }

        // Normalize legacy values before constraining the enum.
        DB::table('projects')
            ->whereIn('type', ['hnd', 'nd'])
            ->update(['type' => 'undergraduate']);

        DB::table('project_topics')
            ->whereIn('academic_level', ['hnd', 'nd'])
            ->update(['academic_level' => 'undergraduate']);

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE `projects` MODIFY `type` ENUM('undergraduate','postgraduate') NOT NULL");
            DB::statement("ALTER TABLE `project_topics` MODIFY `academic_level` ENUM('undergraduate','postgraduate') NOT NULL");
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            return;
        }

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE `projects` MODIFY `type` ENUM('undergraduate','postgraduate','hnd','nd') NOT NULL");
            DB::statement("ALTER TABLE `project_topics` MODIFY `academic_level` ENUM('undergraduate','postgraduate','hnd','nd') NOT NULL");
        }
    }
};

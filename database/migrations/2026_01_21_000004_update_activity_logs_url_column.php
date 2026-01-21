<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('activity_logs', 'url')) {
            DB::statement('ALTER TABLE activity_logs MODIFY url TEXT NULL');
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('activity_logs', 'url')) {
            DB::statement('ALTER TABLE activity_logs MODIFY url VARCHAR(500) NULL');
        }
    }
};

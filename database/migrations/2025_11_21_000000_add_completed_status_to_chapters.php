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
        // SQLite (test env) already includes the new status in the base create migration; skip incompatible ALTER.
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE chapters MODIFY COLUMN status ENUM('not_started', 'draft', 'in_review', 'approved', 'completed') DEFAULT 'not_started'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        // We cannot easily remove 'completed' if there are rows with that status,
        // but strictly speaking we should revert to the old list.
        // For safety in dev, we might skip this or map 'completed' to 'draft' first.
        // Here we will just revert the definition.
        DB::statement("UPDATE chapters SET status = 'draft' WHERE status = 'completed'");
        DB::statement("ALTER TABLE chapters MODIFY COLUMN status ENUM('not_started', 'draft', 'in_review', 'approved') DEFAULT 'not_started'");
    }
};

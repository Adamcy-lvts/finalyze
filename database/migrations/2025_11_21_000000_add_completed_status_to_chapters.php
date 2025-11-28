<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE chapters MODIFY COLUMN status ENUM('not_started', 'draft', 'in_review', 'approved', 'completed') DEFAULT 'not_started'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // We cannot easily remove 'completed' if there are rows with that status,
        // but strictly speaking we should revert to the old list.
        // For safety in dev, we might skip this or map 'completed' to 'draft' first.
        // Here we will just revert the definition.
        DB::statement("UPDATE chapters SET status = 'draft' WHERE status = 'completed'");
        DB::statement("ALTER TABLE chapters MODIFY COLUMN status ENUM('not_started', 'draft', 'in_review', 'approved') DEFAULT 'not_started'");
    }
};

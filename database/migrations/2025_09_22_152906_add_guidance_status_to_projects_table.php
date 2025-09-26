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
        Schema::table('projects', function (Blueprint $table) {
            // Modify the status enum to include 'guidance'
            $table->enum('status', ['setup', 'topic_selection', 'topic_pending_approval', 'topic_approved', 'guidance', 'writing', 'completed'])->default('setup')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            // Revert the status enum to remove 'guidance'
            $table->enum('status', ['setup', 'topic_selection', 'topic_pending_approval', 'topic_approved', 'writing', 'completed'])->default('setup')->change();
        });
    }
};

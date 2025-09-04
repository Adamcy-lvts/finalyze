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
        // The column already exists from previous migration, just update data
        
        // First, modify the status enum to include new values
        DB::statement("ALTER TABLE projects MODIFY COLUMN status ENUM('draft', 'setup', 'planning', 'writing', 'review', 'completed', 'on_hold', 'archived', 'topic_selection', 'topic_pending_approval', 'topic_approved') NOT NULL DEFAULT 'setup'");
        
        // Migrate existing data - move topic-related statuses to topic_status column
        DB::statement("
            UPDATE projects 
            SET topic_status = status 
            WHERE status IN ('topic_selection', 'topic_pending_approval', 'topic_approved')
        ");
        
        // Update main project status to reflect overall project phase
        DB::statement("
            UPDATE projects 
            SET status = CASE 
                WHEN status = 'topic_selection' THEN 'setup'
                WHEN status = 'topic_pending_approval' THEN 'planning'
                WHEN status = 'topic_approved' THEN 'writing'
                ELSE status
            END
        ");
        
        // For projects that don't have topic-related statuses, set appropriate topic_status
        DB::statement("
            UPDATE projects 
            SET topic_status = CASE 
                WHEN status = 'draft' THEN 'not_started'
                WHEN status = 'setup' THEN 'not_started'  
                WHEN status = 'planning' THEN 'topic_selection'
                WHEN status = 'writing' THEN 'topic_approved'
                WHEN status = 'review' THEN 'topic_approved'
                WHEN status = 'completed' THEN 'topic_approved'
                ELSE topic_status
            END
            WHERE topic_status = 'not_started'
        ");
        
        // Finally, clean up the status enum to remove old topic-specific values
        DB::statement("ALTER TABLE projects MODIFY COLUMN status ENUM('draft', 'setup', 'planning', 'writing', 'review', 'completed', 'on_hold', 'archived') NOT NULL DEFAULT 'setup'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restore original status values before dropping column
        DB::statement("
            UPDATE projects 
            SET status = topic_status 
            WHERE topic_status IN ('topic_selection', 'topic_pending_approval', 'topic_approved')
        ");
        
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('topic_status');
        });
    }
};
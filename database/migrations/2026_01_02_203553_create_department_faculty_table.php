<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Creates pivot table for many-to-many relationship between departments and faculties.
     * This allows departments like "Mass Communication" to belong to multiple faculties
     * (e.g., Social Sciences, Arts, or standalone faculty).
     */
    public function up(): void
    {
        Schema::create('department_faculty', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->onDelete('cascade');
            $table->foreignId('faculty_id')->constrained()->onDelete('cascade');
            $table->boolean('is_primary')->default(false); // Primary faculty for this department
            $table->timestamps();

            // Prevent duplicate entries
            $table->unique(['department_id', 'faculty_id']);

            // Index for efficient lookups
            $table->index(['faculty_id', 'department_id']);
        });

        // Migrate existing department-faculty relationships to pivot table
        // This preserves the current faculty_id relationships
        DB::statement('
            INSERT INTO department_faculty (department_id, faculty_id, is_primary, created_at, updated_at)
            SELECT id, faculty_id, 1, NOW(), NOW()
            FROM departments
            WHERE faculty_id IS NOT NULL
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('department_faculty');
    }
};

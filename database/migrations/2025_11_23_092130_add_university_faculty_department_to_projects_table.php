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
            // Add foreign keys for university, faculty, and department
            $table->foreignId('university_id')->nullable()->after('user_id')->constrained()->onDelete('set null');
            $table->foreignId('faculty_id')->nullable()->after('university_id')->constrained()->onDelete('set null');
            $table->foreignId('department_id')->nullable()->after('faculty_id')->constrained()->onDelete('set null');

            // Keep old string columns for backwards compatibility
            // We can migrate data later and potentially remove these columns

            $table->index('university_id');
            $table->index('faculty_id');
            $table->index('department_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropForeign(['university_id']);
            $table->dropForeign(['faculty_id']);
            $table->dropForeign(['department_id']);

            $table->dropColumn(['university_id', 'faculty_id', 'department_id']);
        });
    }
};

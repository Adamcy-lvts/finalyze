<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds custom institution fields for users whose faculty/department
     * isn't in our database. This allows flexibility while keeping
     * structured data when available.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            // Custom faculty name when user selects "Other"
            $table->string('custom_faculty')->nullable()->after('faculty_id');

            // Custom department name when user selects "Other"
            $table->string('custom_department')->nullable()->after('department_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['custom_faculty', 'custom_department']);
        });
    }
};

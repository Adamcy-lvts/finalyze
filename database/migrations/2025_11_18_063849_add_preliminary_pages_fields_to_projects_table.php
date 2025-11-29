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
            $table->string('student_id')->nullable()->after('user_id');
            $table->string('degree')->nullable()->after('type');
            $table->string('degree_abbreviation')->nullable()->after('degree');
            $table->json('certification_signatories')->nullable()->after('supervisor_name');
            $table->text('dedication')->nullable();
            $table->text('acknowledgements')->nullable();
            $table->text('abstract')->nullable();
            $table->text('references')->nullable();
            $table->text('appendices')->nullable();
            $table->json('tables')->nullable();
            $table->json('abbreviations')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn([
                'student_id',
                'degree',
                'degree_abbreviation',
                'certification_signatories',
                'dedication',
                'acknowledgements',
                'abstract',
                'references',
                'appendices',
                'tables',
                'abbreviations',
            ]);
        });
    }
};

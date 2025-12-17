<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('projects')
            ->where('type', 'undergraduate')
            ->where(function ($query) {
                $query->whereNull('degree_abbreviation')->orWhere('degree_abbreviation', '');
            })
            ->update(['degree_abbreviation' => 'B.Sc.']);

        DB::table('projects')
            ->where('type', 'postgraduate')
            ->where(function ($query) {
                $query->whereNull('degree_abbreviation')->orWhere('degree_abbreviation', '');
            })
            ->update(['degree_abbreviation' => 'M.Sc.']);

        DB::table('projects')
            ->where('type', 'undergraduate')
            ->where(function ($query) {
                $query->whereNull('degree')->orWhere('degree', '');
            })
            ->update(['degree' => 'Bachelor of Science']);

        DB::table('projects')
            ->where('type', 'postgraduate')
            ->where(function ($query) {
                $query->whereNull('degree')->orWhere('degree', '');
            })
            ->update(['degree' => 'Master of Science']);
    }

    public function down(): void
    {
        // Irreversible (would require knowing previous values).
    }
};

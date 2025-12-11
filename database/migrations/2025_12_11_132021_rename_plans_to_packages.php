<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('word_packages')
            ->where('slug', 'undergraduate') // slug remains 'undergraduate' or 'standard'? Slug wasn't changed in previous migration, only name. Previous migration targeted slugs 'undergraduate' and 'postgraduate'.
            ->update(['name' => 'Standard Package']);

        DB::table('word_packages')
            ->where('slug', 'postgraduate')
            ->update(['name' => 'Premium Package']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('word_packages')
            ->where('slug', 'undergraduate')
            ->update(['name' => 'Standard Plan']);

        DB::table('word_packages')
            ->where('slug', 'postgraduate')
            ->update(['name' => 'Premium Plan']);
    }
};

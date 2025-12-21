<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('defense_messages', function (Blueprint $table) {
            $table->boolean('is_follow_up')->default(false)->after('panelist_persona');
        });
    }

    public function down(): void
    {
        Schema::table('defense_messages', function (Blueprint $table) {
            $table->dropColumn('is_follow_up');
        });
    }
};

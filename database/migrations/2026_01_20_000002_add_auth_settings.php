<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('system_settings')) {
            return;
        }

        $key = 'auth.require_email_verification';
        $exists = DB::table('system_settings')->where('key', $key)->exists();

        if ($exists) {
            return;
        }

        DB::table('system_settings')->insert([
            'key' => $key,
            'value' => json_encode(true),
            'type' => 'boolean',
            'group' => 'auth',
            'description' => 'Require users to verify email before accessing the app.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('system_settings')) {
            return;
        }

        DB::table('system_settings')->where('key', 'auth.require_email_verification')->delete();
    }
};

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
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('word_balance')->default(0)->after('remember_token');
            $table->unsignedInteger('total_words_purchased')->default(0)->after('word_balance');
            $table->unsignedInteger('total_words_used')->default(0)->after('total_words_purchased');
            $table->unsignedInteger('bonus_words_received')->default(0)->after('total_words_used');
            $table->boolean('received_signup_bonus')->default(false)->after('bonus_words_received');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'word_balance',
                'total_words_purchased',
                'total_words_used',
                'bonus_words_received',
                'received_signup_bonus',
            ]);
        });
    }
};

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
        Schema::table('chat_conversations', function (Blueprint $table) {
            $table->string('task_type')->default('assist')->after('message_type');
            $table->integer('user_rating')->nullable()->after('ai_model');
            $table->boolean('is_helpful')->nullable()->after('user_rating');
            $table->text('user_feedback')->nullable()->after('is_helpful');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chat_conversations', function (Blueprint $table) {
            $table->dropColumn(['task_type', 'user_rating', 'is_helpful', 'user_feedback']);
        });
    }
};

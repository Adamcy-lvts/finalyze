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
        Schema::create('openai_credit_settings', function (Blueprint $table) {
            $table->id();
            $table->decimal('initial_balance', 10, 4)->default(0)->comment('Initial credit balance in USD (what you topped up)');
            $table->timestamp('balance_set_at')->nullable()->comment('When the initial balance was last set');
            $table->string('notes')->nullable()->comment('Optional notes about this balance setting');
            $table->timestamps();
        });

        // Insert default row
        DB::table('openai_credit_settings')->insert([
            'initial_balance' => config('ai.manual_credit_balance', 0),
            'balance_set_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('openai_credit_settings');
    }
};

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
        Schema::create('banks', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('paystack_id')->unique();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->string('code');
            $table->string('longcode')->nullable();
            $table->string('gateway')->nullable();
            $table->boolean('pay_with_bank')->default(false);
            $table->boolean('pay_with_bank_transfer')->default(false);
            $table->boolean('active')->default(true);
            $table->boolean('is_deleted')->default(false);
            $table->string('country', 64)->nullable();
            $table->string('currency', 8)->nullable();
            $table->string('type', 32)->nullable();
            $table->string('nip_sort_code')->nullable();
            $table->timestamps();

            $table->index(['country', 'currency', 'active']);
            $table->index('code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('banks');
    }
};

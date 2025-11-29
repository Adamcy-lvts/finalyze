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
        Schema::create('word_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Transaction type
            $table->enum('type', [
                'purchase',     // Bought words via payment
                'bonus',        // Signup bonus, referral, promo
                'usage',        // Consumed words (generation, AI assist)
                'refund',       // Refunded due to failed generation
                'adjustment',   // Manual adjustment by admin
                'expiry',       // If we ever add expiration (future)
            ]);

            // Word change (positive = add, negative = deduct)
            $table->integer('words'); // Can be negative for usage
            $table->unsignedInteger('balance_after'); // Running balance after this transaction

            // Description for user
            $table->string('description'); // "Generated Chapter 3", "Purchased Undergraduate Package"

            // Reference to what caused this transaction
            $table->string('reference_type')->nullable(); // "payment", "chapter", "generation", etc.
            $table->unsignedBigInteger('reference_id')->nullable(); // ID of the related model

            // Additional context
            $table->json('metadata')->nullable(); // Extra data (chapter title, AI feature used, etc.)

            $table->timestamps();

            // Indexes
            $table->index(['user_id', 'created_at']);
            $table->index(['user_id', 'type']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('word_transactions');
    }
};

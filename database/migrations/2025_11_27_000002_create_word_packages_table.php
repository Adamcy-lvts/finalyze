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
        Schema::create('word_packages', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // "Undergraduate Project", "Small Top-up"
            $table->string('slug')->unique(); // "undergraduate", "topup-small"
            $table->enum('type', ['project', 'topup']); // Project tier or top-up pack
            $table->string('tier')->nullable(); // "undergraduate", "postgraduate" for project types
            $table->unsignedInteger('words'); // Number of words included
            $table->unsignedInteger('price'); // Price in kobo (₦15,000 = 1500000 kobo)
            $table->string('currency', 3)->default('NGN');
            $table->text('description')->nullable();
            $table->json('features')->nullable(); // List of features for display
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_popular')->default(false); // For highlighting
            $table->json('metadata')->nullable(); // Flexible storage for future fields
            $table->timestamps();

            $table->index(['type', 'is_active']);
            $table->index('sort_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('word_packages');
    }
};

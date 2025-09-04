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
        Schema::create('user_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('default_university')->nullable();
            $table->string('default_course')->nullable();
            $table->enum('preferred_mode', ['auto', 'manual'])->default('auto');
            $table->string('citation_style')->default('apa');
            $table->boolean('email_notifications')->default(true);
            $table->json('writing_preferences')->nullable(); // tone, complexity, etc.
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_preferences');
    }
};

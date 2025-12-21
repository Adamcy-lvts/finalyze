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
        Schema::create('guest_topic_downloads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_topic_id')->constrained('project_topics')->onDelete('cascade');
            $table->string('student_name');
            $table->string('email')->nullable();
            $table->string('university');
            $table->string('faculty');
            $table->string('department');
            $table->string('course');
            $table->string('matric_no')->nullable();
            $table->string('academic_level');
            $table->string('ip_address')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('guest_topic_downloads');
    }
};

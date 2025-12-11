<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('word_packages')->insert([
            'name' => 'Free Starter',
            'slug' => 'free-starter',
            'type' => 'project', // Treating as project package for now, or could vary
            'tier' => null,
            'words' => 5000,
            'price' => 0,
            'currency' => 'NGN',
            'description' => 'Get started for free. Perfect for testing and small tasks.',
            'features' => json_encode([
                '5,000 credits included',
                'Access to basic AI features',
                'Limited exports',
                'Credits never expire',
                'One-time claim per user'
            ]),
            'sort_order' => 0, // Show first
            'is_active' => true,
            'is_popular' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('word_packages')->where('slug', 'free-starter')->delete();
    }
};

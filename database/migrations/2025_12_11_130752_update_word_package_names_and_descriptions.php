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
        // Update Undergraduate to Standard
        DB::table('word_packages')
            ->where('slug', 'undergraduate')
            ->update([
                'name' => 'Standard Plan',
                'description' => 'Perfect for final year projects. Includes enough credits for full project generation plus revisions.',
                'features' => json_encode([
                    '25,000 credits allocation',
                    'Full 5-chapter project generation',
                    'All AI features included',
                    'Defense question preparation',
                    'Unlimited exports (DOCX, PDF)',
                    'Literature mining from 4+ databases',
                    'Credits never expire',
                ]),
            ]);

        // Update Postgraduate to Premium
        DB::table('word_packages')
            ->where('slug', 'postgraduate')
            ->update([
                'name' => 'Premium Plan',
                'description' => 'Designed for extensive research and dissertations. Extended credit allocation for comprehensive work.',
                'features' => json_encode([
                    '40,000 credits allocation',
                    'Full 6-chapter dissertation generation',
                    'All AI features included',
                    'Advanced defense preparation',
                    'Unlimited exports (DOCX, PDF)',
                    'Priority literature mining',
                    'Credits never expire',
                ]),
            ]);

        // Update Top-ups to use "credits" instead of "words"
        $topups = DB::table('word_packages')->where('type', 'topup')->get();
        foreach ($topups as $topup) {
            $features = json_decode($topup->features, true) ?? [];
            $newFeatures = array_map(function($feature) {
                return str_replace(['words', 'Words'], ['credits', 'Credits'], $feature);
            }, $features);
            
            DB::table('word_packages')
                ->where('id', $topup->id)
                ->update([
                    'description' => str_replace(['words', 'Words'], ['credits', 'Credits'], $topup->description),
                    'features' => json_encode($newFeatures),
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert Standard to Undergraduate
        DB::table('word_packages')
            ->where('slug', 'undergraduate')
            ->update([
                'name' => 'Undergraduate Project',
                'description' => 'Perfect for HND and BSc final year projects. Includes enough words for full project generation plus revisions.',
                'features' => json_encode([
                    '25,000 words allocation',
                    'Full 5-chapter project generation',
                    'All AI features included',
                    'Defense question preparation',
                    'Unlimited exports (DOCX, PDF)',
                    'Literature mining from 4+ databases',
                    'Words never expire',
                ]),
            ]);

        // Revert Premium to Postgraduate
        DB::table('word_packages')
            ->where('slug', 'postgraduate')
            ->update([
                'name' => 'Postgraduate Project',
                'description' => 'Designed for MSc, MBA, and PGD dissertations. Extended word allocation for comprehensive research.',
                'features' => json_encode([
                    '40,000 words allocation',
                    'Full 6-chapter dissertation generation',
                    'All AI features included',
                    'Advanced defense preparation',
                    'Unlimited exports (DOCX, PDF)',
                    'Priority literature mining',
                    'Words never expire',
                ]),
            ]);
            
         // Revert Top-ups
        $topups = DB::table('word_packages')->where('type', 'topup')->get();
        foreach ($topups as $topup) {
            $features = json_decode($topup->features, true) ?? [];
            $newFeatures = array_map(function($feature) {
                return str_replace(['credits', 'Credits'], ['words', 'Words'], $feature);
            }, $features);
            
            DB::table('word_packages')
                ->where('id', $topup->id)
                ->update([
                    'description' => str_replace(['credits', 'Credits'], ['words', 'Words'], $topup->description),
                    'features' => json_encode($newFeatures),
                ]);
        }
    }
};

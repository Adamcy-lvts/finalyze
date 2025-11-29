<?php

namespace Database\Seeders;

use App\Models\WordPackage;
use Illuminate\Database\Seeder;

class WordPackageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $packages = [
            // Project Packages
            [
                'name' => 'Undergraduate Project',
                'slug' => 'undergraduate',
                'type' => WordPackage::TYPE_PROJECT,
                'tier' => WordPackage::TIER_UNDERGRADUATE,
                'words' => 25000,
                'price' => 1500000, // ₦15,000 in kobo
                'currency' => 'NGN',
                'description' => 'Perfect for HND and BSc final year projects. Includes enough words for full project generation plus revisions.',
                'features' => [
                    '25,000 words allocation',
                    'Full 5-chapter project generation',
                    'All AI features included',
                    'Defense question preparation',
                    'Unlimited exports (DOCX, PDF)',
                    'Literature mining from 4+ databases',
                    'Words never expire',
                ],
                'sort_order' => 1,
                'is_active' => true,
                'is_popular' => true,
            ],
            [
                'name' => 'Postgraduate Project',
                'slug' => 'postgraduate',
                'type' => WordPackage::TYPE_PROJECT,
                'tier' => WordPackage::TIER_POSTGRADUATE,
                'words' => 40000,
                'price' => 2500000, // ₦25,000 in kobo
                'currency' => 'NGN',
                'description' => 'Designed for MSc, MBA, and PGD dissertations. Extended word allocation for comprehensive research.',
                'features' => [
                    '40,000 words allocation',
                    'Full 6-chapter dissertation generation',
                    'All AI features included',
                    'Advanced defense preparation',
                    'Unlimited exports (DOCX, PDF)',
                    'Priority literature mining',
                    'Words never expire',
                ],
                'sort_order' => 2,
                'is_active' => true,
                'is_popular' => false,
            ],

            // Top-up Packages
            [
                'name' => 'Small Top-up',
                'slug' => 'topup-small',
                'type' => WordPackage::TYPE_TOPUP,
                'tier' => null,
                'words' => 5000,
                'price' => 250000, // ₦2,500 in kobo
                'currency' => 'NGN',
                'description' => 'Quick top-up for minor revisions or additional AI assistance.',
                'features' => [
                    '5,000 additional words',
                    'Use across any project',
                    'Never expires',
                ],
                'sort_order' => 10,
                'is_active' => true,
                'is_popular' => false,
            ],
            [
                'name' => 'Medium Top-up',
                'slug' => 'topup-medium',
                'type' => WordPackage::TYPE_TOPUP,
                'tier' => null,
                'words' => 15000,
                'price' => 600000, // ₦6,000 in kobo
                'currency' => 'NGN',
                'description' => 'Best value top-up for significant content additions.',
                'features' => [
                    '15,000 additional words',
                    'Use across any project',
                    'Never expires',
                    '20% savings vs small pack',
                ],
                'sort_order' => 11,
                'is_active' => true,
                'is_popular' => true,
            ],
            [
                'name' => 'Large Top-up',
                'slug' => 'topup-large',
                'type' => WordPackage::TYPE_TOPUP,
                'tier' => null,
                'words' => 30000,
                'price' => 1000000, // ₦10,000 in kobo
                'currency' => 'NGN',
                'description' => 'Maximum value pack for extensive rework or multiple projects.',
                'features' => [
                    '30,000 additional words',
                    'Use across any project',
                    'Never expires',
                    '33% savings vs small pack',
                ],
                'sort_order' => 12,
                'is_active' => true,
                'is_popular' => false,
            ],
        ];

        foreach ($packages as $package) {
            WordPackage::updateOrCreate(
                ['slug' => $package['slug']],
                $package
            );
        }

        $this->command->info('Word packages seeded successfully!');
    }
}

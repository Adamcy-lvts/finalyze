<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => bcrypt('password'),
            ]
        );

        // Seed faculty structures first (required for faculty chapters)
        $this->call([
            ProjectCategorySeeder::class,
            FacultyStructureSeeder::class,
            FacultyChapterSeeder::class,
        ]);

        // Seed universities, faculties, and departments
        $this->call([
            UniversitySeeder::class,
            FacultySeeder::class,
            DepartmentSeeder::class,
            FeatureFlagSeeder::class,
            SystemSettingSeeder::class,
            AdminRoleSeeder::class,
        ]);
    }
}

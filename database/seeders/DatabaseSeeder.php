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
        // Seed the primary admin account (idempotent for prod/staging)
        User::updateOrCreate(
            ['email' => 'devcentric.studio@gmail.com'],
            [
                'name' => 'Adam Mohammed',
                'password' => bcrypt('@Midnight22'),
            ]
        );

        // Seed faculty structures first (required for faculty chapters)
        $this->call([
            ProjectCategorySeeder::class,
            FacultyStructureSeeder::class,
            FacultyChapterSeeder::class,
            WordPackageSeeder::class,
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

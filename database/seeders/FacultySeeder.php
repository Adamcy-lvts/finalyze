<?php

namespace Database\Seeders;

use App\Models\Faculty;
use App\Models\FacultyStructure;
use Illuminate\Database\Seeder;

class FacultySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get faculty structures
        $science = FacultyStructure::where('faculty_slug', 'science')->first();
        $engineering = FacultyStructure::where('faculty_slug', 'engineering')->first();
        $socialSciences = FacultyStructure::where('faculty_slug', 'social-sciences')->first();
        $managementScience = FacultyStructure::where('faculty_slug', 'management-science')->first();
        $medical = FacultyStructure::where('faculty_slug', 'medical')->first();
        $law = FacultyStructure::where('faculty_slug', 'law')->first();

        $faculties = [
            [
                'faculty_structure_id' => $science?->id,
                'name' => 'Science',
                'slug' => 'science',
                'description' => 'Faculty of Science - Natural and Physical Sciences',
                'sort_order' => 1,
            ],
            [
                'faculty_structure_id' => $engineering?->id,
                'name' => 'Engineering',
                'slug' => 'engineering',
                'description' => 'Faculty of Engineering - All Engineering disciplines',
                'sort_order' => 2,
            ],
            [
                'faculty_structure_id' => $socialSciences?->id,
                'name' => 'Social Sciences',
                'slug' => 'social-sciences',
                'description' => 'Faculty of Social Sciences',
                'sort_order' => 3,
            ],
            [
                'faculty_structure_id' => null, // No structure template yet
                'name' => 'Arts',
                'slug' => 'arts',
                'description' => 'Faculty of Arts - Languages, Literature, History, Philosophy',
                'sort_order' => 4,
            ],
            [
                'faculty_structure_id' => $managementScience?->id,
                'name' => 'Management Sciences',
                'slug' => 'management-sciences',
                'description' => 'Faculty of Management Sciences - Business, Accounting, Finance',
                'sort_order' => 5,
            ],
            [
                'faculty_structure_id' => $medical?->id,
                'name' => 'Medicine',
                'slug' => 'medicine',
                'description' => 'Faculty of Medicine - Medicine, Nursing, Pharmacy',
                'sort_order' => 6,
            ],
            [
                'faculty_structure_id' => $law?->id,
                'name' => 'Law',
                'slug' => 'law',
                'description' => 'Faculty of Law',
                'sort_order' => 7,
            ],
            [
                'faculty_structure_id' => null,
                'name' => 'Education',
                'slug' => 'education',
                'description' => 'Faculty of Education',
                'sort_order' => 8,
            ],
            [
                'faculty_structure_id' => null,
                'name' => 'Agriculture',
                'slug' => 'agriculture',
                'description' => 'Faculty of Agriculture',
                'sort_order' => 9,
            ],
            [
                'faculty_structure_id' => null,
                'name' => 'Environmental Sciences',
                'slug' => 'environmental-sciences',
                'description' => 'Faculty of Environmental Sciences - Architecture, Urban Planning, Estate Management',
                'sort_order' => 10,
            ],
            [
                'faculty_structure_id' => null,
                'name' => 'Veterinary Medicine',
                'slug' => 'veterinary-medicine',
                'description' => 'Faculty of Veterinary Medicine',
                'sort_order' => 11,
            ],
            [
                'faculty_structure_id' => null,
                'name' => 'Pharmacy',
                'slug' => 'pharmacy',
                'description' => 'Faculty of Pharmacy',
                'sort_order' => 12,
            ],
            [
                'faculty_structure_id' => $engineering?->id,
                'name' => 'Technology',
                'slug' => 'technology',
                'description' => 'Faculty of Technology - Applied Sciences and Engineering',
                'sort_order' => 13,
            ],
            [
                'faculty_structure_id' => $socialSciences?->id,
                'name' => 'Communication and Media Studies',
                'slug' => 'communication-and-media-studies',
                'description' => 'Faculty of Communication and Media Studies - Journalism, Mass Communication, Public Relations',
                'sort_order' => 14,
            ],
        ];

        foreach ($faculties as $faculty) {
            Faculty::updateOrCreate(
                ['slug' => $faculty['slug']],
                array_merge($faculty, [
                    'is_active' => true,
                ])
            );
        }
    }
}

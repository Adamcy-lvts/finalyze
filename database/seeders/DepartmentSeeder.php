<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Faculty;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get faculties
        $science = Faculty::where('slug', 'science')->first();
        $engineering = Faculty::where('slug', 'engineering')->first();
        $socialSciences = Faculty::where('slug', 'social-sciences')->first();
        $arts = Faculty::where('slug', 'arts')->first();
        $managementSciences = Faculty::where('slug', 'management-sciences')->first();
        $medicine = Faculty::where('slug', 'medicine')->first();
        $law = Faculty::where('slug', 'law')->first();
        $education = Faculty::where('slug', 'education')->first();
        $agriculture = Faculty::where('slug', 'agriculture')->first();
        $environmental = Faculty::where('slug', 'environmental-sciences')->first();
        $veterinary = Faculty::where('slug', 'veterinary-medicine')->first();
        $pharmacy = Faculty::where('slug', 'pharmacy')->first();
        $technology = Faculty::where('slug', 'technology')->first();
        $communication = Faculty::where('slug', 'communication-and-media-studies')->first();

        $departments = [
            // Science Departments
            ['faculty_id' => $science?->id, 'name' => 'Computer Science', 'slug' => 'computer-science', 'code' => 'CSC', 'sort_order' => 1],
            ['faculty_id' => $science?->id, 'name' => 'Mathematics', 'slug' => 'mathematics', 'code' => 'MTH', 'sort_order' => 2],
            ['faculty_id' => $science?->id, 'name' => 'Physics', 'slug' => 'physics', 'code' => 'PHY', 'sort_order' => 3],
            ['faculty_id' => $science?->id, 'name' => 'Chemistry', 'slug' => 'chemistry', 'code' => 'CHM', 'sort_order' => 4],
            ['faculty_id' => $science?->id, 'name' => 'Biology', 'slug' => 'biology', 'code' => 'BIO', 'sort_order' => 5],
            ['faculty_id' => $science?->id, 'name' => 'Microbiology', 'slug' => 'microbiology', 'code' => 'MCB', 'sort_order' => 6],
            ['faculty_id' => $science?->id, 'name' => 'Biochemistry', 'slug' => 'biochemistry', 'code' => 'BCH', 'sort_order' => 7],
            ['faculty_id' => $science?->id, 'name' => 'Statistics', 'slug' => 'statistics', 'code' => 'STA', 'sort_order' => 8],
            ['faculty_id' => $science?->id, 'name' => 'Geology', 'slug' => 'geology', 'code' => 'GEO', 'sort_order' => 9],
            ['faculty_id' => $science?->id, 'name' => 'Zoology', 'slug' => 'zoology', 'code' => 'ZOO', 'sort_order' => 10],
            ['faculty_id' => $science?->id, 'name' => 'Botany', 'slug' => 'botany', 'code' => 'BOT', 'sort_order' => 11],

            // Engineering Departments
            ['faculty_id' => $engineering?->id, 'name' => 'Computer Engineering', 'slug' => 'computer-engineering', 'code' => 'CPE', 'sort_order' => 1],
            ['faculty_id' => $engineering?->id, 'name' => 'Electrical Engineering', 'slug' => 'electrical-engineering', 'code' => 'EEE', 'sort_order' => 2],
            ['faculty_id' => $engineering?->id, 'name' => 'Mechanical Engineering', 'slug' => 'mechanical-engineering', 'code' => 'MEE', 'sort_order' => 3],
            ['faculty_id' => $engineering?->id, 'name' => 'Civil Engineering', 'slug' => 'civil-engineering', 'code' => 'CVE', 'sort_order' => 4],
            ['faculty_id' => $engineering?->id, 'name' => 'Chemical Engineering', 'slug' => 'chemical-engineering', 'code' => 'CHE', 'sort_order' => 5],
            ['faculty_id' => $engineering?->id, 'name' => 'Petroleum Engineering', 'slug' => 'petroleum-engineering', 'code' => 'PTE', 'sort_order' => 6],
            ['faculty_id' => $engineering?->id, 'name' => 'Industrial Engineering', 'slug' => 'industrial-engineering', 'code' => 'IND', 'sort_order' => 7],
            ['faculty_id' => $engineering?->id, 'name' => 'Mechatronics Engineering', 'slug' => 'mechatronics-engineering', 'code' => 'MTE', 'sort_order' => 8],
            ['faculty_id' => $engineering?->id, 'name' => 'Agricultural Engineering', 'slug' => 'agricultural-engineering', 'code' => 'AGE', 'sort_order' => 9],
            ['faculty_id' => $engineering?->id, 'name' => 'Systems Engineering', 'slug' => 'systems-engineering', 'code' => 'SYE', 'sort_order' => 10],

            // Social Sciences Departments
            ['faculty_id' => $socialSciences?->id, 'name' => 'Economics', 'slug' => 'economics', 'code' => 'ECO', 'sort_order' => 1],
            ['faculty_id' => $socialSciences?->id, 'name' => 'Political Science', 'slug' => 'political-science', 'code' => 'POL', 'sort_order' => 2],
            ['faculty_id' => $socialSciences?->id, 'name' => 'Sociology', 'slug' => 'sociology', 'code' => 'SOC', 'sort_order' => 3],
            ['faculty_id' => $socialSciences?->id, 'name' => 'Psychology', 'slug' => 'psychology', 'code' => 'PSY', 'sort_order' => 4],
            ['faculty_id' => $socialSciences?->id, 'name' => 'Geography', 'slug' => 'geography', 'code' => 'GEG', 'sort_order' => 5],
            ['faculty_id' => $socialSciences?->id, 'name' => 'International Relations', 'slug' => 'international-relations', 'code' => 'INT', 'sort_order' => 6],
            ['faculty_id' => $socialSciences?->id, 'name' => 'Public Administration', 'slug' => 'public-administration', 'code' => 'PAD', 'sort_order' => 7],
            ['faculty_id' => $socialSciences?->id, 'name' => 'Social Work', 'slug' => 'social-work', 'code' => 'SWK', 'sort_order' => 8],
            ['faculty_id' => $socialSciences?->id, 'name' => 'Demography and Social Statistics', 'slug' => 'demography-social-statistics', 'code' => 'DSS', 'sort_order' => 9],

            // Arts Departments
            ['faculty_id' => $arts?->id, 'name' => 'English Language', 'slug' => 'english-language', 'code' => 'ENG', 'sort_order' => 1],
            ['faculty_id' => $arts?->id, 'name' => 'History', 'slug' => 'history', 'code' => 'HIS', 'sort_order' => 2],
            ['faculty_id' => $arts?->id, 'name' => 'Philosophy', 'slug' => 'philosophy', 'code' => 'PHI', 'sort_order' => 3],
            ['faculty_id' => $arts?->id, 'name' => 'Religious Studies', 'slug' => 'religious-studies', 'code' => 'REL', 'sort_order' => 4],
            ['faculty_id' => $arts?->id, 'name' => 'Linguistics', 'slug' => 'linguistics', 'code' => 'LIN', 'sort_order' => 5],
            ['faculty_id' => $arts?->id, 'name' => 'French', 'slug' => 'french', 'code' => 'FRE', 'sort_order' => 6],
            ['faculty_id' => $arts?->id, 'name' => 'Arabic', 'slug' => 'arabic', 'code' => 'ARB', 'sort_order' => 7],
            ['faculty_id' => $arts?->id, 'name' => 'Theatre Arts', 'slug' => 'theatre-arts', 'code' => 'THA', 'sort_order' => 8],
            ['faculty_id' => $arts?->id, 'name' => 'Music', 'slug' => 'music', 'code' => 'MUS', 'sort_order' => 9],
            ['faculty_id' => $arts?->id, 'name' => 'Fine and Applied Arts', 'slug' => 'fine-applied-arts', 'code' => 'FAA', 'sort_order' => 10],

            // Management Sciences Departments
            ['faculty_id' => $managementSciences?->id, 'name' => 'Accounting', 'slug' => 'accounting', 'code' => 'ACC', 'sort_order' => 1],
            ['faculty_id' => $managementSciences?->id, 'name' => 'Business Administration', 'slug' => 'business-administration', 'code' => 'BUS', 'sort_order' => 2],
            ['faculty_id' => $managementSciences?->id, 'name' => 'Finance', 'slug' => 'finance', 'code' => 'FIN', 'sort_order' => 3],
            ['faculty_id' => $managementSciences?->id, 'name' => 'Marketing', 'slug' => 'marketing', 'code' => 'MKT', 'sort_order' => 4],
            ['faculty_id' => $managementSciences?->id, 'name' => 'Banking and Finance', 'slug' => 'banking-finance', 'code' => 'BNF', 'sort_order' => 5],
            ['faculty_id' => $managementSciences?->id, 'name' => 'Insurance', 'slug' => 'insurance', 'code' => 'INS', 'sort_order' => 6],
            ['faculty_id' => $managementSciences?->id, 'name' => 'Industrial Relations', 'slug' => 'industrial-relations', 'code' => 'IRL', 'sort_order' => 7],
            ['faculty_id' => $managementSciences?->id, 'name' => 'Actuarial Science', 'slug' => 'actuarial-science', 'code' => 'ACT', 'sort_order' => 8],

            // Medicine Departments
            ['faculty_id' => $medicine?->id, 'name' => 'Medicine and Surgery', 'slug' => 'medicine-surgery', 'code' => 'MED', 'sort_order' => 1],
            ['faculty_id' => $medicine?->id, 'name' => 'Nursing Science', 'slug' => 'nursing-science', 'code' => 'NUR', 'sort_order' => 2],
            ['faculty_id' => $medicine?->id, 'name' => 'Medical Laboratory Science', 'slug' => 'medical-laboratory-science', 'code' => 'MLS', 'sort_order' => 3],
            ['faculty_id' => $medicine?->id, 'name' => 'Dentistry', 'slug' => 'dentistry', 'code' => 'DEN', 'sort_order' => 4],
            ['faculty_id' => $medicine?->id, 'name' => 'Public Health', 'slug' => 'public-health', 'code' => 'PBH', 'sort_order' => 5],
            ['faculty_id' => $medicine?->id, 'name' => 'Physiotherapy', 'slug' => 'physiotherapy', 'code' => 'PHT', 'sort_order' => 6],
            ['faculty_id' => $medicine?->id, 'name' => 'Radiography', 'slug' => 'radiography', 'code' => 'RAD', 'sort_order' => 7],
            ['faculty_id' => $medicine?->id, 'name' => 'Anatomy', 'slug' => 'anatomy', 'code' => 'ANA', 'sort_order' => 8],
            ['faculty_id' => $medicine?->id, 'name' => 'Physiology', 'slug' => 'physiology', 'code' => 'PHS', 'sort_order' => 9],

            // Law Departments
            ['faculty_id' => $law?->id, 'name' => 'Common Law', 'slug' => 'common-law', 'code' => 'CLW', 'sort_order' => 1],
            ['faculty_id' => $law?->id, 'name' => 'Private and Property Law', 'slug' => 'private-property-law', 'code' => 'PPL', 'sort_order' => 2],
            ['faculty_id' => $law?->id, 'name' => 'Public Law', 'slug' => 'public-law', 'code' => 'PUL', 'sort_order' => 3],
            ['faculty_id' => $law?->id, 'name' => 'Commercial and Industrial Law', 'slug' => 'commercial-industrial-law', 'code' => 'CIL', 'sort_order' => 4],
            ['faculty_id' => $law?->id, 'name' => 'International Law', 'slug' => 'international-law', 'code' => 'ITL', 'sort_order' => 5],

            // Education Departments
            ['faculty_id' => $education?->id, 'name' => 'Educational Administration', 'slug' => 'educational-administration', 'code' => 'EDA', 'sort_order' => 1],
            ['faculty_id' => $education?->id, 'name' => 'Educational Psychology', 'slug' => 'educational-psychology', 'code' => 'EDP', 'sort_order' => 2],
            ['faculty_id' => $education?->id, 'name' => 'Curriculum Studies', 'slug' => 'curriculum-studies', 'code' => 'CUR', 'sort_order' => 3],
            ['faculty_id' => $education?->id, 'name' => 'Science Education', 'slug' => 'science-education', 'code' => 'SED', 'sort_order' => 4],
            ['faculty_id' => $education?->id, 'name' => 'Arts and Social Science Education', 'slug' => 'arts-social-science-education', 'code' => 'ASE', 'sort_order' => 5],
            ['faculty_id' => $education?->id, 'name' => 'Adult Education', 'slug' => 'adult-education', 'code' => 'ADE', 'sort_order' => 6],
            ['faculty_id' => $education?->id, 'name' => 'Educational Technology', 'slug' => 'educational-technology', 'code' => 'EDT', 'sort_order' => 7],
            ['faculty_id' => $education?->id, 'name' => 'Guidance and Counselling', 'slug' => 'guidance-counselling', 'code' => 'GNC', 'sort_order' => 8],

            // Agriculture Departments
            ['faculty_id' => $agriculture?->id, 'name' => 'Agronomy', 'slug' => 'agronomy', 'code' => 'AGR', 'sort_order' => 1],
            ['faculty_id' => $agriculture?->id, 'name' => 'Animal Science', 'slug' => 'animal-science', 'code' => 'ANS', 'sort_order' => 2],
            ['faculty_id' => $agriculture?->id, 'name' => 'Soil Science', 'slug' => 'soil-science', 'code' => 'SOS', 'sort_order' => 3],
            ['faculty_id' => $agriculture?->id, 'name' => 'Crop Science', 'slug' => 'crop-science', 'code' => 'CPS', 'sort_order' => 4],
            ['faculty_id' => $agriculture?->id, 'name' => 'Fisheries', 'slug' => 'fisheries', 'code' => 'FSH', 'sort_order' => 5],
            ['faculty_id' => $agriculture?->id, 'name' => 'Agricultural Economics', 'slug' => 'agricultural-economics', 'code' => 'AEC', 'sort_order' => 6],
            ['faculty_id' => $agriculture?->id, 'name' => 'Agricultural Extension', 'slug' => 'agricultural-extension', 'code' => 'AEX', 'sort_order' => 7],
            ['faculty_id' => $agriculture?->id, 'name' => 'Food Science and Technology', 'slug' => 'food-science-technology', 'code' => 'FST', 'sort_order' => 8],

            // Environmental Sciences Departments
            ['faculty_id' => $environmental?->id, 'name' => 'Architecture', 'slug' => 'architecture', 'code' => 'ARC', 'sort_order' => 1],
            ['faculty_id' => $environmental?->id, 'name' => 'Urban and Regional Planning', 'slug' => 'urban-regional-planning', 'code' => 'URP', 'sort_order' => 2],
            ['faculty_id' => $environmental?->id, 'name' => 'Estate Management', 'slug' => 'estate-management', 'code' => 'ESM', 'sort_order' => 3],
            ['faculty_id' => $environmental?->id, 'name' => 'Quantity Surveying', 'slug' => 'quantity-surveying', 'code' => 'QSV', 'sort_order' => 4],
            ['faculty_id' => $environmental?->id, 'name' => 'Building Technology', 'slug' => 'building-technology', 'code' => 'BLD', 'sort_order' => 5],
            ['faculty_id' => $environmental?->id, 'name' => 'Surveying and Geoinformatics', 'slug' => 'surveying-geoinformatics', 'code' => 'SVG', 'sort_order' => 6],

            // Veterinary Medicine Departments
            ['faculty_id' => $veterinary?->id, 'name' => 'Veterinary Medicine', 'slug' => 'veterinary-medicine', 'code' => 'VET', 'sort_order' => 1],
            ['faculty_id' => $veterinary?->id, 'name' => 'Veterinary Anatomy', 'slug' => 'veterinary-anatomy', 'code' => 'VAN', 'sort_order' => 2],
            ['faculty_id' => $veterinary?->id, 'name' => 'Veterinary Physiology', 'slug' => 'veterinary-physiology', 'code' => 'VPH', 'sort_order' => 3],
            ['faculty_id' => $veterinary?->id, 'name' => 'Veterinary Pathology', 'slug' => 'veterinary-pathology', 'code' => 'VPA', 'sort_order' => 4],
            ['faculty_id' => $veterinary?->id, 'name' => 'Veterinary Public Health', 'slug' => 'veterinary-public-health', 'code' => 'VPB', 'sort_order' => 5],

            // Pharmacy Departments
            ['faculty_id' => $pharmacy?->id, 'name' => 'Clinical Pharmacy', 'slug' => 'clinical-pharmacy', 'code' => 'CPH', 'sort_order' => 1],
            ['faculty_id' => $pharmacy?->id, 'name' => 'Pharmaceutical Chemistry', 'slug' => 'pharmaceutical-chemistry', 'code' => 'PCH', 'sort_order' => 2],
            ['faculty_id' => $pharmacy?->id, 'name' => 'Pharmacology', 'slug' => 'pharmacology', 'code' => 'PCL', 'sort_order' => 3],
            ['faculty_id' => $pharmacy?->id, 'name' => 'Pharmaceutics', 'slug' => 'pharmaceutics', 'code' => 'PHC', 'sort_order' => 4],
            ['faculty_id' => $pharmacy?->id, 'name' => 'Pharmacognosy', 'slug' => 'pharmacognosy', 'code' => 'PHG', 'sort_order' => 5],

            // Technology Departments
            ['faculty_id' => $technology?->id, 'name' => 'Information Technology', 'slug' => 'information-technology', 'code' => 'IFT', 'sort_order' => 1],
            ['faculty_id' => $technology?->id, 'name' => 'Software Engineering', 'slug' => 'software-engineering', 'code' => 'SWE', 'sort_order' => 2],
            ['faculty_id' => $technology?->id, 'name' => 'Cyber Security', 'slug' => 'cyber-security', 'code' => 'CYB', 'sort_order' => 3],
            ['faculty_id' => $technology?->id, 'name' => 'Data Science', 'slug' => 'data-science', 'code' => 'DTS', 'sort_order' => 4],

            // Communication and Media Studies Departments
            ['faculty_id' => $communication?->id, 'name' => 'Mass Communication', 'slug' => 'mass-communication', 'code' => 'MCM', 'sort_order' => 1],
            ['faculty_id' => $communication?->id, 'name' => 'Journalism', 'slug' => 'journalism', 'code' => 'JRN', 'sort_order' => 2],
            ['faculty_id' => $communication?->id, 'name' => 'Public Relations', 'slug' => 'public-relations', 'code' => 'PRE', 'sort_order' => 3],
            ['faculty_id' => $communication?->id, 'name' => 'Broadcasting', 'slug' => 'broadcasting', 'code' => 'BRC', 'sort_order' => 4],
            ['faculty_id' => $communication?->id, 'name' => 'Film and Multimedia', 'slug' => 'film-multimedia', 'code' => 'FLM', 'sort_order' => 5],
            ['faculty_id' => $communication?->id, 'name' => 'Advertising', 'slug' => 'advertising', 'code' => 'ADV', 'sort_order' => 6],
        ];

        foreach ($departments as $department) {
            if ($department['faculty_id']) {
                Department::updateOrCreate(
                    ['slug' => $department['slug']],
                    array_merge($department, [
                        'is_active' => true,
                    ])
                );
            }
        }
    }
}

<?php

namespace Database\Seeders;

use App\Models\FacultyChapter;
use App\Models\FacultySection;
use App\Models\FacultyStructure;
use Illuminate\Database\Seeder;

class FacultyChapterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Seeding faculty chapters and sections...');

        // Clear existing data
        FacultySection::query()->delete();
        FacultyChapter::query()->delete();

        $facultyStructures = FacultyStructure::all();
        $this->command->info("Processing {$facultyStructures->count()} faculty structures...");

        foreach ($facultyStructures as $facultyStructure) {
            $this->command->info("Processing {$facultyStructure->faculty_name}...");
            $this->migrateFacultyStructure($facultyStructure);
        }

        $this->command->info('Faculty chapters and sections seeded successfully!');
    }

    private function migrateFacultyStructure(FacultyStructure $facultyStructure): void
    {
        $structure = $facultyStructure->default_structure;

        if (! isset($structure['chapters']['default'])) {
            $this->command->warn("No default chapters found for {$facultyStructure->faculty_name}");

            return;
        }

        $chapters = $structure['chapters']['default'];

        foreach ($chapters as $chapterData) {
            $this->migrateChapter($facultyStructure, $chapterData);
            $this->command->line("  ✓ Migrated chapter: {$chapterData['title']} with ".count($chapterData['sections'] ?? []).' sections');
        }
    }

    private function migrateChapter(FacultyStructure $facultyStructure, array $chapterData): void
    {
        // Create a single chapter record that applies to all academic levels
        $facultyChapter = FacultyChapter::create([
            'faculty_structure_id' => $facultyStructure->id,
            'academic_level' => 'all', // Single record for all levels
            'project_type' => 'thesis',
            'chapter_number' => $chapterData['number'],
            'chapter_title' => $chapterData['title'],
            'description' => $chapterData['description'] ?? null,
            'target_word_count' => $chapterData['word_count'] ?? 3000,
            'completion_threshold' => $chapterData['completion_threshold'] ?? 80,
            'is_required' => $chapterData['is_required'] ?? true,
            'sort_order' => $chapterData['number'],
        ]);

        // Migrate sections for this chapter
        $this->migrateSections($facultyChapter, $chapterData);
    }

    private function migrateSections(FacultyChapter $facultyChapter, array $chapterData): void
    {
        if (! isset($chapterData['sections']) || ! is_array($chapterData['sections'])) {
            return;
        }

        foreach ($chapterData['sections'] as $sectionData) {
            FacultySection::create([
                'faculty_chapter_id' => $facultyChapter->id,
                'section_number' => $sectionData['number'],
                'section_title' => $sectionData['title'],
                'description' => $sectionData['description'] ?? null,
                'target_word_count' => $sectionData['word_count'] ?? 500,
                'is_required' => $sectionData['is_required'] ?? true,
                'writing_guidance' => $sectionData['guidance'] ?? null,
                'sort_order' => (int) filter_var($sectionData['number'], FILTER_SANITIZE_NUMBER_INT),
            ]);
        }
    }
}

<?php

namespace App\Services\Defense;

use App\Models\Chapter;
use App\Models\FacultyStructure;
use App\Models\Project;

class ChapterTypeDetector
{
    public function detect(Project $project, Chapter $chapter): string
    {
        $type = $this->detectFromFacultyStructure($project, (int) $chapter->chapter_number);
        if ($type) {
            return $type;
        }

        $type = $this->inferFromTitle($chapter->title);
        if ($type !== 'general') {
            return $type;
        }

        return $this->fallbackFromNumber((int) $chapter->chapter_number);
    }

    private function detectFromFacultyStructure(Project $project, int $chapterNumber): ?string
    {
        $project->loadMissing('facultyRelation.structure.chapters');

        $structure = $project->facultyRelation?->structure;
        if (! $structure && $project->faculty) {
            $structure = FacultyStructure::forFaculty($project->faculty)->active()->with('chapters')->first();
        }

        if (! $structure) {
            return null;
        }

        $facultyChapter = $structure->chapters->firstWhere('chapter_number', $chapterNumber);
        if (! $facultyChapter) {
            return null;
        }

        return $this->inferFromTitle($facultyChapter->chapter_title);
    }

    private function inferFromTitle(?string $title): string
    {
        $t = strtolower(trim((string) $title));

        if ($t === '') {
            return 'general';
        }

        if (str_contains($t, 'introduction')) {
            return 'introduction';
        }
        if (str_contains($t, 'literature')) {
            return 'literature_review';
        }
        if (str_contains($t, 'method')) {
            return 'methodology';
        }
        if (str_contains($t, 'result') || str_contains($t, 'finding')) {
            return 'results';
        }
        if (str_contains($t, 'discussion')) {
            return 'discussion';
        }
        if (str_contains($t, 'conclusion')) {
            return 'conclusion';
        }

        return 'general';
    }

    private function fallbackFromNumber(int $number): string
    {
        return match ($number) {
            1 => 'introduction',
            2 => 'literature_review',
            3 => 'methodology',
            4 => 'results',
            5 => 'discussion',
            default => 'general',
        };
    }
}

<?php

namespace App\Console\Commands;

use App\Models\FacultyChapter;
use App\Models\FacultySection;
use App\Models\FacultyStructure;
use Illuminate\Console\Command;

class AddReferenceSectionCommand extends Command
{
    protected $signature = 'faculty:add-references';

    protected $description = 'Add References section to the last chapter of all faculty structures';

    public function handle()
    {
        $this->info('Adding References sections to last chapters...');

        $faculties = FacultyStructure::with(['chapters.sections'])->get();

        foreach ($faculties as $faculty) {
            $this->info("Processing Faculty: {$faculty->faculty_name}");

            foreach ($faculty->chapters->groupBy('academic_level') as $level => $chapters) {
                $this->info("  Academic Level: $level");

                // Get the last chapter for this academic level
                $lastChapter = $chapters->sortBy('chapter_number')->last();

                if (! $lastChapter) {
                    $this->warn("    No chapters found for $level level");

                    continue;
                }

                $this->info("    Last Chapter: {$lastChapter->chapter_number} - {$lastChapter->chapter_title}");

                // Check if it already has a references section
                $hasReferences = $lastChapter->sections->contains(function ($section) {
                    return str_contains(strtolower($section->section_title), 'reference');
                });

                if ($hasReferences) {
                    $this->info('    ✓ Already has References section');

                    continue;
                }

                // Add References section to the last chapter
                $this->addReferencesSection($lastChapter, $faculty);
                $this->info('    ✓ Added References section');
            }

            $this->newLine();
        }

        $this->info('✓ Completed adding References sections to all faculty structures');

        return 0;
    }

    private function addReferencesSection(FacultyChapter $chapter, FacultyStructure $faculty): void
    {
        // Get the highest section number for proper ordering
        $lastSection = $chapter->sections()->orderBy('sort_order')->get()->last();
        $nextSectionNumber = $lastSection ? $lastSection->sort_order + 1 : 1;

        // Determine section numbering based on chapter
        $sectionNumber = $chapter->chapter_number.'.'.($chapter->sections()->count() + 1);

        // Create References section
        FacultySection::create([
            'faculty_chapter_id' => $chapter->id,
            'section_number' => $sectionNumber,
            'section_title' => 'References',
            'description' => 'Complete list of all sources cited in the academic work, formatted according to the required citation style.',
            'writing_guidance' => $this->getReferencesGuidance($faculty->faculty_name),
            'tips' => $this->getReferenceTips($faculty->faculty_name),
            'target_word_count' => 0, // References don't have word count
            'is_required' => true,
            'sort_order' => $nextSectionNumber,
        ]);
    }

    private function getReferencesGuidance(string $facultyName): string
    {
        $facultyLower = strtolower($facultyName);

        if (str_contains($facultyLower, 'law')) {
            return 'List all legal authorities cited in your work including cases, statutes, books, journal articles, and other legal materials. Use NALT, OSCOLA, or the citation style specified by your faculty. Arrange alphabetically by author surname or case name. Include pinpoint references where applicable. Separate primary sources (cases, statutes) from secondary sources (textbooks, journal articles) if required by your faculty.';
        }

        if (str_contains($facultyLower, 'medical')) {
            return 'List all medical and scientific literature cited in your research. Use Vancouver or APA style as specified by your faculty. Include journal articles, textbooks, clinical guidelines, government reports, and other medical sources. Arrange numerically (Vancouver) or alphabetically by author (APA). Ensure all sources are from reputable medical journals and authoritative medical institutions.';
        }

        if (str_contains($facultyLower, 'engineering')) {
            return 'List all technical and scientific references cited in your project. Use IEEE, APA, or the citation style specified by your faculty. Include journal articles, conference proceedings, technical standards, patents, textbooks, and reliable online technical resources. Arrange alphabetically by author or numerically. Prioritize peer-reviewed technical journals and authoritative engineering sources.';
        }

        if (str_contains($facultyLower, 'science')) {
            return 'List all scientific literature and sources cited in your research. Use the citation style specified by your faculty (often APA or journal-specific formats). Include peer-reviewed journal articles, textbooks, conference proceedings, and authoritative scientific databases. Arrange alphabetically by author surname. Ensure all sources are from reputable scientific journals and recognized scientific institutions.';
        }

        // Default for Social Sciences, Management, etc.
        return 'List all sources cited in your academic work following the required citation style (typically APA). Include books, journal articles, reports, theses, and reliable online sources. Arrange alphabetically by author surname. Ensure all sources are credible, recent (within 5-10 years for most citations), and relevant to your research topic. Include DOI or URL for online sources where applicable.';
    }

    private function getReferenceTips(string $facultyName): array
    {
        $facultyLower = strtolower($facultyName);

        if (str_contains($facultyLower, 'law')) {
            return [
                'Separate primary sources (cases, statutes) from secondary sources (textbooks, articles)',
                'Use proper legal citation format with neutral citations where available',
                'Include pinpoint references to specific pages or paragraphs',
                'List cases in chronological order within alphabetical arrangement by first party name',
                'Verify all citations are accurate and properly formatted according to faculty guidelines',
            ];
        }

        if (str_contains($facultyLower, 'medical')) {
            return [
                'Use PubMed IDs (PMID) for journal articles where available',
                'Prioritize recent publications (within last 5-7 years) for current medical practice',
                'Include DOI numbers for all journal articles that have them',
                'Use official journal abbreviations as listed in Index Medicus',
                'Ensure all medical sources are from peer-reviewed journals or authoritative medical institutions',
            ];
        }

        if (str_contains($facultyLower, 'engineering')) {
            return [
                'Include DOI numbers for all technical papers and conference proceedings',
                'Use official abbreviations for IEEE and other technical journals',
                'Include technical standards, patents, and industry specifications where relevant',
                'Prioritize recent publications for rapidly evolving technical fields',
                'Verify all technical specifications and standards are current versions',
            ];
        }

        // Default tips for all other faculties
        return [
            'Use consistent citation style throughout (APA, Harvard, MLA as specified)',
            'Arrange alphabetically by author surname, or numerically if using numbered style',
            'Include DOI or stable URL for online sources',
            'Ensure all in-text citations have corresponding reference entries',
            'Use recent sources (within 5-10 years) for most citations, older sources for foundational concepts',
            'Double-check all author names, publication years, and page numbers for accuracy',
        ];
    }
}

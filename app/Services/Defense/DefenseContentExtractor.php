<?php

namespace App\Services\Defense;

use App\Models\Chapter;
use App\Models\Project;
use App\Services\AIContentGenerator;
use App\Services\ChapterContentAnalysisService;

class DefenseContentExtractor
{
    public function __construct(
        private AIContentGenerator $aiGenerator,
        private HtmlContentParser $htmlParser,
        private ChapterTypeDetector $typeDetector,
        private ChapterContentAnalysisService $contentAnalysis
    ) {}

    public function extractFromProject(Project $project): array
    {
        $project->loadMissing('chapters', 'universityRelation', 'facultyRelation.structure.chapters');

        $extractedData = [
            'project_meta' => $this->extractProjectMeta($project),
            'chapters' => [],
        ];

        foreach ($project->chapters->sortBy('chapter_number') as $chapter) {
            $chapterType = $this->typeDetector->detect($project, $chapter);
            $extractedData['chapters'][] = $this->extractFromChapter($chapter, $chapterType);
        }

        return $extractedData;
    }

    private function extractProjectMeta(Project $project): array
    {
        return [
            'title' => (string) ($project->title ?? ''),
            'topic' => (string) ($project->topic ?? ''),
            'field_of_study' => (string) ($project->field_of_study ?? ''),
            'academic_level' => (string) ($project->academic_level ?? $project->type ?? ''),
            'university' => (string) ($project->universityRelation?->name ?? $project->university ?? ''),
        ];
    }

    private function extractFromChapter(Chapter $chapter, string $type): array
    {
        $wordCount = $this->contentAnalysis->getChapterWordCount($chapter);
        if ($wordCount < 200) {
            return [
                'number' => $chapter->chapter_number,
                'title' => $chapter->title,
                'type' => $type,
                'word_count' => $wordCount,
                'extracted_data' => null,
                'skip_reason' => 'Insufficient content (< 200 words)',
            ];
        }

        $parsed = $this->htmlParser->parse($chapter->content ?? '');
        $prompt = $this->buildExtractionPrompt($type, $parsed, $chapter);
        $raw = $this->aiGenerator->generate($prompt, [
            'feature' => 'defense_slide_extraction',
            'model' => 'gpt-4o-mini',
            'temperature' => 0.1,
        ]);

        $extracted = $this->parseExtractionResponse($raw);
        if (! empty($parsed['tables'])) {
            $extracted['tables_extracted'] = $parsed['tables'];
        }

        return [
            'number' => $chapter->chapter_number,
            'title' => $chapter->title,
            'type' => $type,
            'word_count' => $wordCount,
            'extracted_data' => $extracted,
        ];
    }

    private function buildExtractionPrompt(string $type, array $parsed, Chapter $chapter): string
    {
        $typeLabel = strtoupper(str_replace('_', ' ', $type));
        $fields = $this->getFieldsForType($type);
        $tableCount = count($parsed['tables'] ?? []);
        $citationCount = count($parsed['citations'] ?? []);

        $parsedContent = [
            'headings' => array_slice($parsed['headings'] ?? [], 0, 20),
            'paragraphs' => array_slice($parsed['paragraphs'] ?? [], 0, 20),
            'lists' => array_slice($parsed['lists'] ?? [], 0, 10),
            'tables' => array_slice($parsed['tables'] ?? [], 0, 5),
            'statistics' => array_slice($parsed['statistics'] ?? [], 0, 20),
            'citations' => array_slice($parsed['citations'] ?? [], 0, 20),
        ];

        $parsedJson = json_encode($parsedContent, JSON_UNESCAPED_SLASHES);
        $statsJson = json_encode($parsed['statistics'] ?? [], JSON_UNESCAPED_SLASHES);

        return <<<PROMPT
You are an academic content analyzer. Extract structured data from this {$typeLabel} chapter.

=== CHAPTER TITLE ===
{$chapter->title}

=== PARSED CONTENT (JSON) ===
{$parsedJson}

=== PRE-EXTRACTED DATA ===
Tables found: {$tableCount}
Statistics: {$statsJson}
Citations: {$citationCount}

=== EXTRACT THESE FIELDS ===
{$fields}

=== OUTPUT ===
Return ONLY valid JSON. Use null for fields not found in content.
PROMPT;
    }

    private function getFieldsForType(string $type): string
    {
        return match ($type) {
            'introduction' => <<<'FIELDS'
1. problem_statement
2. research_gap
3. general_objective
4. specific_objectives (array)
5. research_questions (array)
6. scope.coverage
7. scope.delimitations
8. significance.theoretical
9. significance.practical
10. significance.beneficiaries (array)
FIELDS,
            'literature_review' => <<<'FIELDS'
1. key_concepts (array of {term, definition})
2. theoretical_framework (array of {theory, application})
3. empirical_studies (array of {citation, findings, relevance})
4. research_gap
5. conceptual_framework.constructs (array)
6. conceptual_framework.relationships (array)
FIELDS,
            'methodology' => <<<'FIELDS'
1. research_design.type
2. research_design.justification
3. population.target
4. population.sampling_technique
5. population.sample_size
6. data_collection.instrument
7. data_collection.variables_measured (array)
8. data_analysis.techniques (array)
9. data_analysis.software
10. validity_reliability.reliability_value
FIELDS,
            'results' => <<<'FIELDS'
1. response_rate
2. demographics (array of {category, distribution})
3. key_findings (array of {objective_addressed, finding, statistic, interpretation})
4. hypothesis_results (array of {hypothesis, result, evidence})
FIELDS,
            'discussion', 'conclusion' => <<<'FIELDS'
1. summary_of_findings (array)
2. literature_comparison (array of {finding, agrees_with, differs_from})
3. implications.theoretical (array)
4. implications.practical (array)
5. limitations (array)
6. recommendations.practical (array)
7. recommendations.future_research (array)
8. conclusion_statement
FIELDS,
            default => <<<'FIELDS'
1. key_points (array)
2. summary
3. notable_statistics (array)
FIELDS,
        };
    }

    private function parseExtractionResponse(string $response): array
    {
        $clean = trim($response);
        if ($clean === '') {
            throw new \RuntimeException('Empty extraction response.');
        }

        if (str_contains($clean, '```')) {
            $clean = preg_replace('/```json\s*|\s*```/i', '', $clean);
        }

        $start = strpos($clean, '{');
        $end = strrpos($clean, '}');
        if ($start !== false && $end !== false) {
            $clean = substr($clean, $start, $end - $start + 1);
        }

        $decoded = json_decode($clean, true);
        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
            throw new \RuntimeException('Failed to parse extraction JSON.');
        }

        return $decoded;
    }
}

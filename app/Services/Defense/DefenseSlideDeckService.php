<?php

namespace App\Services\Defense;

use App\Models\Project;
use App\Services\ChapterContentAnalysisService;

class DefenseSlideDeckService
{
    public function __construct(private ChapterContentAnalysisService $contentAnalysis) {}

    public function buildSlidePrompt(Project $project): string
    {
        $context = $this->buildProjectContext($project);

        return <<<PROMPT
You are an academic defense coach. Create a complete thesis defense slide deck from title slide to conclusion.

{$context}

Requirements:
- Never exceed 20 slides total.
- Include: Title slide, research problem, objectives, methodology, results, contributions, limitations, future work, conclusion.
- Keep each slide concise and defense-ready. Write slides as if they will be presented, not as an outline.
- Use only the project-specific details provided in the context. Avoid generic filler or placeholder statements.
- Reference concrete facts from the chapters (methods, datasets, results, metrics, tools, outcomes, scope) whenever available.
- If a required slide lacks sufficient project details, explicitly state "Not provided in project content" in a bullet rather than inventing facts.
- Provide presentation-ready bullets (short, specific statements), not just headings.

Return strict JSON with this shape:
{
  "slides": [
    {
      "title": "Slide title",
      "bullets": ["Bullet 1", "Bullet 2"],
      "layout": "title|bullets|image_left|image_right|two_column",
      "visuals": "Suggested visual/diagram",
      "speaker_notes": "Short speaker notes",
      "charts": [
        {
          "type": "bar|line|pie|scatter|area",
          "title": "Chart title",
          "x": ["Label 1", "Label 2"],
          "series": [
            { "name": "Series A", "data": [1, 2] }
          ]
        }
      ],
      "tables": [
        {
          "title": "Table title",
          "columns": ["Column 1", "Column 2"],
          "rows": [
            ["Cell 1", "Cell 2"]
          ]
        }
      ]
    }
  ]
}

Return JSON only. No markdown or extra text.
PROMPT;
    }

    public function buildSlidePromptFromExtraction(Project $project, array $extractedData): string
    {
        $meta = $extractedData['project_meta'] ?? [];
        $title = (string) ($meta['title'] ?? $project->title ?? '');
        $topic = (string) ($meta['topic'] ?? $project->topic ?? '');
        $university = (string) ($meta['university'] ?? $project->universityRelation?->name ?? '');
        $academicLevel = (string) ($meta['academic_level'] ?? $project->academic_level ?? $project->type ?? '');
        $payload = json_encode($extractedData, JSON_UNESCAPED_SLASHES);

        return <<<PROMPT
You are an expert academic defense presentation designer creating a COMPREHENSIVE thesis defense slide deck.

=== PROJECT ===
Title: {$title}
Topic: {$topic}
University: {$university}
Academic Level: {$academicLevel}

=== EXTRACTED DATA ===
{$payload}

=== CRITICAL RULES ===
1. Create DETAILED, DEFENSE-READY slides with rich content
2. Use EXACT statistics, percentages, p-values from extracted data
3. If data is null, elaborate based on project context
4. Methodology and Results sections MUST span multiple slides
5. Speaker notes should contain 3-5 detailed talking points per slide

=== CONTENT TYPE RULES ===
USE BULLETS (content_type: "bullets") FOR:
- Research Objectives (numbered list of objectives)
- Limitations (list of constraints)
- Recommendations (actionable items)
- Thank You slide

USE PARAGRAPHS (content_type: "paragraphs") FOR:
- Research Background (narrative explanation)
- Research Problem (detailed problem description)
- Research Gap (explanation of what's missing)
- Literature Review / Theoretical Framework (narrative with citations)
- Methodology slides (descriptive explanation of methods)
- Results / Findings (narrative with embedded statistics)
- Discussion (comparative analysis narrative)
- Implications (detailed explanations)
- Conclusion (summary narrative)

USE MIXED (content_type: "mixed") FOR:
- Slides needing both a heading/intro paragraph AND bullet points

=== REQUIRED SLIDE STRUCTURE (18-24 slides) ===

OPENING (2 slides):
1. Title Slide - Project title, student, supervisor, university, date
2. Research Background - Context and motivation (PARAGRAPHS)

PROBLEM & OBJECTIVES (3 slides):
3. Research Problem - Detailed problem statement (PARAGRAPHS - 2-3 paragraphs)
4. Research Gap - What's missing in literature (PARAGRAPHS)
5. Research Objectives - General + specific objectives (BULLETS - numbered)

LITERATURE (2-3 slides):
6. Theoretical Framework - Key theories (PARAGRAPHS with theory explanations)
7. Conceptual Framework - Variable relationships (PARAGRAPHS describing the model)
8. Literature Summary - Key studies (MIXED - intro paragraph + bullet findings) [if data available]

METHODOLOGY (3-4 slides):
9. Research Design - Design type & justification (PARAGRAPHS)
10. Population & Sampling - Target population, technique, sample size (PARAGRAPHS)
11. Data Collection - Instruments, variables, validity (PARAGRAPHS)
12. Data Analysis - Techniques, software, tests (PARAGRAPHS)

RESULTS & FINDINGS (4-6 slides):
13. Demographics/Response Rate - Participant characteristics (MIXED - paragraph + table if available)
14-17. Key Findings - ONE SLIDE PER FINDING (PARAGRAPHS with statistics embedded in text)
18. Hypothesis Summary - Results of hypothesis tests (MIXED or table)

DISCUSSION & CONCLUSION (4-5 slides):
19. Discussion - Comparison with literature (PARAGRAPHS - 2-3 paragraphs)
20. Implications - Theoretical and practical (MIXED - can have subheadings)
21. Limitations - Study constraints (BULLETS - 4-6 limitations)
22. Recommendations - For practice and research (BULLETS - numbered)
23. Conclusion - Summary of contributions (PARAGRAPHS)

CLOSING (1 slide):
24. Thank You & Q&A - Acknowledgments (BULLETS)

=== OUTPUT FORMAT ===
Return strict JSON:
{
  "slides": [
    {
      "title": "Slide Title",
      "content_type": "bullets|paragraphs|mixed",
      "bullets": ["Point 1", "Point 2"],
      "paragraphs": ["First paragraph with full sentences...", "Second paragraph..."],
      "headings": [
        {"heading": "Subheading", "content": "Paragraph under this heading..."}
      ],
      "layout": "title|content|two_column",
      "visuals": "Visual suggestion",
      "speaker_notes": "Talking points...",
      "charts": [],
      "tables": []
    }
  ]
}

FIELD USAGE:
- content_type "bullets": Use "bullets" array only
- content_type "paragraphs": Use "paragraphs" array (2-4 paragraphs of 40-80 words each)
- content_type "mixed": Use "headings" array with heading+content pairs, or "paragraphs" + "bullets"

Return JSON only. No markdown code blocks.
PROMPT;
    }

    public function extractSlidesPayload(string $response): ?array
    {
        $clean = trim($response);
        if ($clean === '') {
            return null;
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
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        return $decoded;
    }

    public function normalizeSlides(array $payload, int $maxSlides = 30): array
    {
        $slides = $payload['slides'] ?? [];
        if (! is_array($slides)) {
            return [];
        }

        $slides = array_values(array_filter($slides, fn ($slide) => is_array($slide)));
        $slides = array_slice($slides, 0, $maxSlides);

        return array_map(function (array $slide) {
            $contentType = (string) ($slide['content_type'] ?? 'bullets');
            if (! in_array($contentType, ['bullets', 'paragraphs', 'mixed'], true)) {
                $contentType = 'bullets';
            }

            return [
                'title' => (string) ($slide['title'] ?? 'Untitled Slide'),
                'content_type' => $contentType,
                'bullets' => array_values(array_filter(
                    (array) ($slide['bullets'] ?? []),
                    fn ($bullet) => is_string($bullet) && trim($bullet) !== ''
                )),
                'paragraphs' => array_values(array_filter(
                    (array) ($slide['paragraphs'] ?? []),
                    fn ($para) => is_string($para) && trim($para) !== ''
                )),
                'headings' => array_values(array_filter(
                    (array) ($slide['headings'] ?? []),
                    fn ($h) => is_array($h) && ! empty($h['heading'])
                )),
                'layout' => (string) ($slide['layout'] ?? 'content'),
                'visuals' => (string) ($slide['visuals'] ?? ''),
                'speaker_notes' => (string) ($slide['speaker_notes'] ?? ''),
                'charts' => array_values(array_filter(
                    (array) ($slide['charts'] ?? []),
                    fn ($chart) => is_array($chart)
                )),
                'tables' => array_values(array_filter(
                    (array) ($slide['tables'] ?? []),
                    fn ($table) => is_array($table)
                )),
            ];
        }, $slides);
    }

    private function buildProjectContext(Project $project): string
    {
        $project->loadMissing('chapters', 'universityRelation');

        $context = "Project Title: {$project->title}\n";
        $context .= "Topic: {$project->topic}\n";
        $context .= "Field of Study: {$project->field_of_study}\n";
        $context .= "University: {$project->universityRelation?->name}\n";
        $context .= "Course: {$project->course}\n";

        $chapters = $project->chapters->sortBy('chapter_number');
        if ($chapters->isNotEmpty()) {
            $context .= "\n=== CHAPTER SUMMARIES ===\n";
            foreach ($chapters as $chapter) {
                if (! $chapter->content) {
                    continue;
                }

                $preview = substr(strip_tags($chapter->content), 0, 3000);
                $wordCount = $this->contentAnalysis->getChapterWordCount($chapter);
                $context .= "\nChapter {$chapter->chapter_number}: {$chapter->title} (Word Count: {$wordCount})\n{$preview}\n";
            }
        }

        return $context;
    }
}

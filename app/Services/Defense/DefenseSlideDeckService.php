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
- Keep each slide concise and defense-ready.
- Use only the project-specific details provided in the context. Avoid generic filler or placeholder statements.
- Reference concrete facts from the chapters (methods, datasets, results, metrics, tools, outcomes, scope) whenever available.
- If a required slide lacks sufficient project details, explicitly state "Not provided in project content" in a bullet rather than inventing facts.

Return strict JSON with this shape:
{
  "slides": [
    {
      "title": "Slide title",
      "bullets": ["Bullet 1", "Bullet 2"],
      "visuals": "Suggested visual/diagram",
      "speaker_notes": "Short speaker notes"
    }
  ]
}

Return JSON only. No markdown or extra text.
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

    public function normalizeSlides(array $payload, int $maxSlides = 20): array
    {
        $slides = $payload['slides'] ?? [];
        if (! is_array($slides)) {
            return [];
        }

        $slides = array_values(array_filter($slides, fn ($slide) => is_array($slide)));
        $slides = array_slice($slides, 0, $maxSlides);

        return array_map(function (array $slide) {
            return [
                'title' => (string) ($slide['title'] ?? 'Untitled Slide'),
                'bullets' => array_values(array_filter(
                    (array) ($slide['bullets'] ?? []),
                    fn ($bullet) => is_string($bullet) && trim($bullet) !== ''
                )),
                'visuals' => (string) ($slide['visuals'] ?? ''),
                'speaker_notes' => (string) ($slide['speaker_notes'] ?? ''),
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

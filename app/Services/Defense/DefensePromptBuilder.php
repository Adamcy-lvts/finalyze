<?php

namespace App\Services\Defense;

use App\Models\Chapter;
use App\Models\Project;
use App\Services\ChapterContentAnalysisService;

class DefensePromptBuilder
{
    public function __construct(private ChapterContentAnalysisService $contentAnalysis)
    {
    }

    public function buildDefenseQuestionsPrompt(Project $project, ?string $chapterContent, string $focus, int $count): string
    {
        $context = "Project Title: {$project->title}\n";
        $context .= "Topic: {$project->topic}\n";
        $context .= "Field of Study: {$project->field_of_study}\n";
        $context .= "University: {$project->universityRelation?->name}\n";
        $context .= "Course: {$project->course}\n";

        $chapters = Chapter::where('project_id', $project->id)
            ->orderBy('chapter_number')
            ->get();

        if ($chapters->isNotEmpty()) {
            $context .= "\n=== PROJECT CONTENT ===\n";

            foreach ($chapters as $chapter) {
                if ($chapter->content && $this->contentAnalysis->hasMinimumWordCountForDefense($chapter)) {
                    $wordCount = $this->contentAnalysis->getChapterWordCount($chapter);
                    $context .= "\n--- Chapter {$chapter->chapter_number}: {$chapter->title} (Word Count: {$wordCount}) ---\n";
                    $chapterPreview = substr($chapter->content, 0, 2000);
                    $context .= $chapterPreview."\n";
                }
            }
        }

        if ($chapterContent && ! $chapters->isNotEmpty()) {
            $context .= "\nChapter Content (Preview):\n".substr($chapterContent, 0, 3000)."...\n";
        }

        $focusInstruction = match ($focus) {
            'methodology' => 'Focus on research methodology, data collection, and analysis methods.',
            'literature' => 'Focus on literature review, theoretical framework, and related works.',
            'findings' => 'Focus on research findings, results, and data interpretation.',
            'theory' => 'Focus on theoretical contributions and conceptual framework.',
            'contribution' => 'Focus on research contributions, implications, and significance.',
            default => 'Cover various aspects including methodology, findings, and contributions.'
        };

        return <<<PROMPT
You are an experienced thesis defense examiner. Based on the following thesis information, generate {$count} potential defense questions that examiners might ask.

{$context}

{$focusInstruction}

For each question, provide:
1. The question itself (challenging but fair)
2. A suggested answer approach (2-3 sentences)
3. Key points to cover (2-3 bullet points)
4. Difficulty level (easy/medium/hard)
5. Category (methodology/literature/findings/theory/contribution)

Format each question as:
QUESTION: [question text]
ANSWER: [suggested answer approach]
KEY_POINTS: • [point 1] • [point 2] • [point 3]
DIFFICULTY: [level]
CATEGORY: [category]
---

Generate thoughtful, academic questions that test deep understanding of the research.
PROMPT;
    }

    public function buildExecutiveBriefingPrompt(Project $project): string
    {
        $context = $this->buildProjectContext($project);

        return <<<PROMPT
You are an academic defense coach. Create an executive briefing for a thesis defense, organized into 4-5 key slides.

{$context}

Return a valid JSON object with this structure:
{
    "slides": [
        {
            "title": "Slide Title (e.g., Overview, Methodology, Findings)",
            "content": "Markdown content for the slide body (bullet points, short paragraphs)."
        }
    ]
}

Ensure the slides cover:
1. Concise Overview
2. Key Methodology
3. Major Findings
4. Implications & Contributions
5. Chapter-by-Chapter Key Points (Brief breakdown)
6. Potential Weak Points (for defense prep)
PROMPT;
    }

    public function buildOpeningStatementPrompt(Project $project, string $statement): string
    {
        $context = $this->buildProjectContext($project);

        return <<<PROMPT
You are an academic defense coach. Evaluate the opening statement below.

{$context}

Opening Statement:
{$statement}

Provide:
- Clarity score (0-100)
- Confidence score (0-100)
- Key strengths (2-3 bullets)
- Improvements (2-3 bullets)
- A revised 60-90 second version.
PROMPT;
    }

    public function buildOpeningStatementGenerationPrompt(Project $project): string
    {
        $context = $this->buildProjectContext($project);

        return <<<PROMPT
You are an academic defense coach. Draft a strong 60-90 second opening statement for a thesis defense.

{$context}

Requirements:
- 120 to 180 words.
- Clear problem framing, core contribution, and practical impact.
- Confident but not exaggerated tone.
- No bullet points, write as a single short speech.
PROMPT;
    }

    public function buildPresentationGuidePrompt(Project $project): string
    {
        $context = $this->buildProjectContext($project);

        return <<<PROMPT
You are an academic defense coach. Create a structured presentation guide for a thesis defense.

{$context}

Return a valid JSON object wrapped in a markdown code block (```json ... ```).
The JSON object must have a "slides" key containing an array of 8-12 slide objects.
Each slide object must have:
- "title": string (e.g., "Introduction", "Methodology")
- "duration": string (e.g., "1 min")
- "content": string (bullet points or short paragraph for the slide body)
- "talking_points": array of strings (key script notes for the speaker)
- "visuals": string (description of recommended visuals/diagrams)

Example format:
```json
{
    "slides": [
        {
            "title": "Introduction",
            "duration": "2 mins",
            "content": "- Hook statement\n- Problem context",
            "talking_points": ["Start with a story...", "Define the core gap..."],
            "visuals": " infographic of the problem"
        }
    ]
}
```
PROMPT;
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
                if ($chapter->content && $this->contentAnalysis->hasMinimumWordCountForDefense($chapter)) {
                    $preview = substr($chapter->content, 0, 1500);
                    $context .= "\nChapter {$chapter->chapter_number}: {$chapter->title}\n{$preview}\n";
                }
            }
        }

        return $context;
    }
}

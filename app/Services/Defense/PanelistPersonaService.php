<?php

namespace App\Services\Defense;

use App\Models\DefenseSession;
use App\Models\Project;
use App\Services\ChapterContentAnalysisService;

class PanelistPersonaService
{
    private const CHAIR_ID = 'generalist';

    private ChapterContentAnalysisService $contentAnalysis;

    private array $personas = [
        'skeptic' => [
            'id' => 'skeptic',
            'name' => 'The Skeptic',
            'role' => 'Critical Reviewer',
            'questioningStyle' => 'aggressive',
            'focusAreas' => ['methodology_flaws', 'sample_size', 'bias', 'generalizability'],
            'difficultyModifier' => 1.2,
        ],
        'methodologist' => [
            'id' => 'methodologist',
            'name' => 'The Methodologist',
            'role' => 'Technical Expert',
            'questioningStyle' => 'methodical',
            'focusAreas' => ['research_design', 'data_analysis', 'validity', 'reliability'],
            'difficultyModifier' => 1.0,
        ],
        'generalist' => [
            'id' => 'generalist',
            'name' => 'The Generalist',
            'role' => 'Value Reviewer',
            'questioningStyle' => 'supportive',
            'focusAreas' => ['contribution', 'practical_implications', 'future_research'],
            'difficultyModifier' => 0.8,
        ],
        'theorist' => [
            'id' => 'theorist',
            'name' => 'The Theorist',
            'role' => 'Framework Expert',
            'questioningStyle' => 'methodical',
            'focusAreas' => ['theoretical_framework', 'literature_gaps', 'conceptual_clarity'],
            'difficultyModifier' => 1.1,
        ],
        'practitioner' => [
            'id' => 'practitioner',
            'name' => 'The Practitioner',
            'role' => 'Industry Expert',
            'questioningStyle' => 'supportive',
            'focusAreas' => ['real_world_application', 'industry_relevance', 'implementation'],
            'difficultyModifier' => 0.9,
        ],
    ];

    public function __construct(ChapterContentAnalysisService $contentAnalysis)
    {
        $this->contentAnalysis = $contentAnalysis;
    }

    public function getPersona(string $type): array
    {
        return $this->personas[$type] ?? $this->personas['generalist'];
    }

    public function getDefaultPersonaIds(): array
    {
        return array_keys($this->personas);
    }

    public function pickNextPersonaId(DefenseSession $session): string
    {
        $panelists = $session->selected_panelists ?: $this->getDefaultPersonaIds();
        $sequence = $this->getChairModeratedSequence($panelists);
        $sequence = array_values(array_unique($sequence));

        if (! $session->question_limit) {
            $index = $session->questions_asked % max(1, count($sequence));

            return $sequence[$index] ?? $this->getDefaultPersonaIds()[0];
        }

        $targets = $this->getPanelistTargets($sequence, $session->question_limit);
        $counts = $this->getPanelistCounts($session, $sequence);
        $startIndex = $session->questions_asked % max(1, count($sequence));

        for ($i = 0; $i < count($sequence); $i++) {
            $candidate = $sequence[($startIndex + $i) % count($sequence)];
            $target = $targets[$candidate] ?? 0;
            $asked = $counts[$candidate] ?? 0;

            if ($asked < $target) {
                return $candidate;
            }
        }

        return $sequence[0] ?? $this->getDefaultPersonaIds()[0];
    }

    public function buildSystemPrompt(string $personaId, Project $project, string $academicLevel): string
    {
        $persona = $this->getPersona($personaId);
        $project->loadMissing('chapters', 'universityRelation');

        $context = "Project Title: {$project->title}\n";
        $context .= "Topic: {$project->topic}\n";
        $context .= "Field of Study: {$project->field_of_study}\n";
        $context .= "University: {$project->universityRelation?->name}\n";
        $context .= "Course: {$project->course}\n";
        $context .= "Academic Level: {$academicLevel}\n";

        $chapters = $project->chapters->sortBy('chapter_number');
        if ($chapters->isNotEmpty()) {
            $context .= "\n=== CHAPTER EXCERPTS ===\n";
            foreach ($chapters as $chapter) {
                if ($chapter->content && $this->contentAnalysis->hasMinimumWordCountForDefense($chapter)) {
                    $preview = substr($chapter->content, 0, 1200);
                    $context .= "\nChapter {$chapter->chapter_number}: {$chapter->title}\n{$preview}\n";
                }
            }
        }

        $style = $persona['questioningStyle'];
        $focusAreas = implode(', ', $persona['focusAreas']);

        return <<<PROMPT
You are {$persona['name']} ({$persona['role']}). Ask one defense question at a time.
Questioning style: {$style}. Focus areas: {$focusAreas}.
Difficulty: {$academicLevel} level.

{$context}

Rules:
- Ask a single, direct question in one paragraph.
- Keep it challenging but fair.
- Do not include analysis or answer guidance.
PROMPT;
    }

    public function buildQuestionPrompt(string $personaId, DefenseSession $session): string
    {
        $persona = $this->getPersona($personaId);
        $remaining = $session->question_limit ? max(0, $session->question_limit - $session->questions_asked) : null;

        $limitNote = $remaining !== null ? "Questions remaining in session: {$remaining}." : '';

        return "Ask the next defense question now. {$limitNote} Persona: {$persona['name']} ({$persona['role']}).";
    }

    public function buildFollowUpPrompt(
        string $personaId,
        string $question,
        string $studentAnswer,
        array $evaluation,
        bool $secondFailure,
        bool $requestHint
    ): string {
        $persona = $this->getPersona($personaId);
        $style = $persona['questioningStyle'];
        $strengths = implode(', ', $evaluation['strengths'] ?? []);
        $improvements = implode(', ', $evaluation['improvements'] ?? []);
        $allowReveal = $secondFailure || $requestHint;

        $revealInstruction = $allowReveal
            ? 'After probing, provide a short model answer if the student is still stuck.'
            : 'Do NOT provide the full answer. Ask a pointed follow-up and give a gentle hint.';

        return <<<PROMPT
You are {$persona['name']} ({$persona['role']}). Questioning style: {$style}.
You asked: "{$question}"
Student answered: "{$studentAnswer}"
Evaluation notes:
- Strengths: {$strengths}
- Improvements: {$improvements}

Task:
- Press the student on the weakest part of the answer.
- Keep it to one short follow-up question + a hint.
- {$revealInstruction}
PROMPT;
    }

    private function getChairModeratedSequence(array $panelists): array
    {
        $unique = array_values(array_unique($panelists));

        if (! in_array(self::CHAIR_ID, $unique, true)) {
            array_unshift($unique, self::CHAIR_ID);
        }

        $ordered = $this->orderPanelists($unique);

        if (count($ordered) <= 1) {
            return $ordered;
        }

        $chairFirst = array_shift($ordered);

        return array_merge([$chairFirst], $ordered, [$chairFirst]);
    }

    private function orderPanelists(array $panelists): array
    {
        $priority = ['methodologist', 'skeptic', 'theorist', 'practitioner'];
        $ordered = [];

        if (in_array(self::CHAIR_ID, $panelists, true)) {
            $ordered[] = self::CHAIR_ID;
        }

        foreach ($priority as $id) {
            if (in_array($id, $panelists, true)) {
                $ordered[] = $id;
            }
        }

        foreach ($panelists as $id) {
            if (! in_array($id, $ordered, true)) {
                $ordered[] = $id;
            }
        }

        return $ordered;
    }

    private function getPanelistCounts(DefenseSession $session, array $panelists): array
    {
        $counts = array_fill_keys($panelists, 0);
        $messages = $session->messages()
            ->where('role', 'panelist')
            ->where('is_follow_up', false)
            ->get(['panelist_persona']);

        foreach ($messages as $message) {
            $persona = $message->panelist_persona;
            if ($persona && array_key_exists($persona, $counts)) {
                $counts[$persona]++;
            }
        }

        return $counts;
    }

    private function getPanelistTargets(array $sequence, int $questionLimit): array
    {
        $targets = array_fill_keys($sequence, 0);
        $count = count($sequence);
        if ($count === 0) {
            return $targets;
        }

        $base = intdiv($questionLimit, $count);
        $remainder = $questionLimit % $count;
        $chairIndex = array_search(self::CHAIR_ID, $sequence, true);

        foreach ($sequence as $index => $id) {
            $targets[$id] = $base;
            if ($remainder > 0 && ($chairIndex === $index || ($chairIndex === false && $index === 0))) {
                $targets[$id] += $remainder;
            }
        }

        return $targets;
    }
}

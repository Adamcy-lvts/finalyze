<?php

namespace App\Services\PromptSystem;

use App\Models\Project;
use App\Models\PromptTemplate;
use App\Services\FacultyStructureService;
use App\Services\PromptSystem\Templates\BasePromptTemplate;
use App\Services\PromptSystem\Templates\Faculty\AgricultureTemplate;
use App\Services\PromptSystem\Templates\Faculty\ArtsTemplate;
use App\Services\PromptSystem\Templates\Faculty\BusinessTemplate;
use App\Services\PromptSystem\Templates\Faculty\EducationTemplate;
use App\Services\PromptSystem\Templates\Faculty\EngineeringTemplate;
use App\Services\PromptSystem\Templates\Faculty\HealthcareTemplate;
use App\Services\PromptSystem\Templates\Faculty\LawTemplate;
use App\Services\PromptSystem\Templates\Faculty\ScienceTemplate;
use App\Services\PromptSystem\Templates\Faculty\SocialScienceTemplate;
use App\Services\PromptSystem\Templates\PromptTemplateInterface;
use Illuminate\Support\Facades\Log;

class PromptRouter
{
    /**
     * Faculty to template class mapping
     */
    private const FACULTY_TEMPLATES = [
        'engineering' => EngineeringTemplate::class,
        'social_science' => SocialScienceTemplate::class,
        'healthcare' => HealthcareTemplate::class,
        'business' => BusinessTemplate::class,
        'science' => ScienceTemplate::class,
        'arts' => ArtsTemplate::class,
        'education' => EducationTemplate::class,
        'law' => LawTemplate::class,
        'agriculture' => AgricultureTemplate::class,
    ];

    public function __construct(
        private ContextMatcher $contextMatcher,
        private ContentDecisionEngine $contentDecisionEngine,
        private PromptBuilder $promptBuilder,
        private FacultyStructureService $facultyStructureService
    ) {}

    /**
     * Build complete prompt for chapter generation
     */
    public function buildPrompt(Project $project, int $chapterNumber): string
    {
        // 1. Match project to academic context
        $context = $this->contextMatcher->match($project);

        $this->applyChapterMeta($project, $chapterNumber);

        Log::info('PromptRouter: Context matched', [
            'project_id' => $project->id,
            'context' => $context,
        ]);

        // 2. Load the appropriate template
        $template = $this->loadTemplate($context, $chapterNumber, $project);

        // 3. Determine content requirements based on context and template
        $requirements = $this->contentDecisionEngine->analyze($project, $chapterNumber, $context, $template);

        Log::info('PromptRouter: Requirements analyzed', [
            'project_id' => $project->id,
            'chapter' => $chapterNumber,
            'tables_required' => count($requirements->getTables()),
            'diagrams_required' => count($requirements->getDiagrams()),
        ]);

        // 4. Build the final prompt
        return $this->promptBuilder->build($project, $chapterNumber, $template, $requirements);
    }

    /**
     * Get system prompt for AI context
     */
    public function getSystemPrompt(Project $project): string
    {
        $context = $this->contextMatcher->match($project);
        $template = $this->loadTemplate($context, 1, $project); // Chapter number doesn't matter for system prompt

        return $template->getSystemPrompt();
    }

    /**
     * Load the appropriate template based on context
     */
    private function loadTemplate(array $context, int $chapterNumber, Project $project): PromptTemplateInterface
    {
        // First, try to load from database (for custom/override templates)
        $dbTemplate = $this->loadFromDatabase($context, $chapterNumber, $project);
        if ($dbTemplate) {
            return $dbTemplate;
        }

        // Fall back to code-based templates
        return $this->loadCodeTemplate($context['faculty']);
    }

    /**
     * Load template from database
     */
    private function loadFromDatabase(array $context, int $chapterNumber, Project $project): ?PromptTemplateInterface
    {
        $chapterType = $this->detectChapterType($project, $chapterNumber);
        $matchingContexts = [];

        // Build list of contexts to search for (in priority order)
        if ($context['project_type']) {
            $matchingContexts[] = ['type' => 'topic_keyword', 'value' => $context['project_type']];
        }
        if ($context['field']) {
            $matchingContexts[] = ['type' => 'field_of_study', 'value' => $context['field']];
        }
        if ($context['course']) {
            $matchingContexts[] = ['type' => 'course', 'value' => $context['course']];
        }
        if ($context['department']) {
            $matchingContexts[] = ['type' => 'department', 'value' => $context['department']];
        }
        $matchingContexts[] = ['type' => 'faculty', 'value' => $context['faculty']];

        // Find the most specific matching template
        foreach ($matchingContexts as $ctx) {
            $template = PromptTemplate::where('context_type', $ctx['type'])
                ->where('context_value', $ctx['value'])
                ->where('is_active', true)
                ->where(function ($query) use ($chapterType) {
                    $query->where('chapter_type', $chapterType)
                        ->orWhereNull('chapter_type');
                })
                ->orderByDesc('priority')
                ->first();

            if ($template) {
                return $this->wrapDatabaseTemplate($template, $context['faculty']);
            }
        }

        return null;
    }

    /**
     * Wrap database template in a PromptTemplateInterface
     */
    private function wrapDatabaseTemplate(PromptTemplate $dbTemplate, string $faculty): PromptTemplateInterface
    {
        // Get the base template for the faculty
        $baseTemplate = $this->loadCodeTemplate($faculty);

        // Create a wrapper that merges database config with base template
        return new class($dbTemplate, $baseTemplate) extends BasePromptTemplate
        {
            public function __construct(
                private PromptTemplate $dbTemplate,
                private PromptTemplateInterface $baseTemplate
            ) {
                $this->priority = $dbTemplate->priority;
            }

            public function getSystemPrompt(): string
            {
                return $this->dbTemplate->getMergedSystemPrompt() ?? $this->baseTemplate->getSystemPrompt();
            }

            public function buildChapterPrompt(Project $project, int $chapterNumber, ContentRequirements $requirements): string
            {
                $templateBody = $this->dbTemplate->getMergedChapterPromptTemplate();
                if ($templateBody) {
                    // Use database template with variable substitution
                    return $this->substituteVariables(
                        $templateBody,
                        $project,
                        $chapterNumber,
                        $requirements
                    );
                }

                return $this->baseTemplate->buildChapterPrompt($project, $chapterNumber, $requirements);
            }

            public function getTableRequirements(int $chapterNumber): array
            {
                $merged = $this->dbTemplate->getMergedTableRequirements();
                if (! empty($merged)) {
                    return $merged;
                }
                return $this->baseTemplate->getTableRequirements($chapterNumber);
            }

            public function getDiagramRequirements(int $chapterNumber): array
            {
                $merged = $this->dbTemplate->getMergedDiagramRequirements();
                if (! empty($merged)) {
                    return $merged;
                }
                return $this->baseTemplate->getDiagramRequirements($chapterNumber);
            }

            public function getCalculationRequirements(int $chapterNumber): array
            {
                $merged = $this->dbTemplate->getMergedCalculationRequirements();
                if (! empty($merged)) {
                    return $merged;
                }
                return $this->baseTemplate->getCalculationRequirements($chapterNumber);
            }

            public function getCodeRequirements(int $chapterNumber): array
            {
                $merged = $this->dbTemplate->getMergedCodeRequirements();
                if (! empty($merged)) {
                    return $merged;
                }
                return $this->baseTemplate->getCodeRequirements($chapterNumber);
            }

            public function getPlaceholderRules(int $chapterNumber): array
            {
                $merged = $this->dbTemplate->getMergedPlaceholderRules();
                if (! empty($merged)) {
                    return $merged;
                }
                return $this->baseTemplate->getPlaceholderRules($chapterNumber);
            }

            public function getRecommendedTools(): array
            {
                $merged = $this->dbTemplate->getMergedRecommendedTools();
                if (! empty($merged)) {
                    return $merged;
                }
                return $this->baseTemplate->getRecommendedTools();
            }

            private function substituteVariables(string $template, Project $project, int $chapterNumber, ContentRequirements $requirements): string
            {
                $variables = [
                    '{{topic}}' => $project->topic,
                    '{{faculty}}' => $project->faculty,
                    '{{department}}' => $project->department,
                    '{{course}}' => $project->course,
                    '{{field_of_study}}' => $project->field_of_study,
                    '{{academic_level}}' => $project->type,
                    '{{university}}' => $project->university,
                    '{{chapter_number}}' => $chapterNumber,
                ];

                return str_replace(array_keys($variables), array_values($variables), $template);
            }
        };
    }

    /**
     * Load code-based template for faculty
     */
    private function loadCodeTemplate(string $faculty): PromptTemplateInterface
    {
        $templateClass = self::FACULTY_TEMPLATES[$faculty] ?? null;

        if ($templateClass && class_exists($templateClass)) {
            return new $templateClass;
        }

        // Return a default template
        return new class extends BasePromptTemplate
        {
            protected int $priority = 0;
        };
    }

    /**
     * Detect chapter type (prefer chapter structure metadata).
     */
    private function detectChapterType(Project $project, int $chapterNumber): string
    {
        $meta = $project->getAttribute('chapter_type');
        if (is_string($meta) && trim($meta) !== '') {
            return $meta;
        }

        return match ($chapterNumber) {
            1 => 'introduction',
            2 => 'literature_review',
            3 => 'methodology',
            4 => 'results',
            5 => 'discussion',
            default => 'general',
        };
    }

    /**
     * Get matched context for debugging/logging
     */
    public function getMatchedContext(Project $project): array
    {
        return $this->contextMatcher->match($project);
    }

    /**
     * Get content requirements for a project/chapter
     */
    public function getContentRequirements(Project $project, int $chapterNumber): ContentRequirements
    {
        $context = $this->contextMatcher->match($project);
        $this->applyChapterMeta($project, $chapterNumber);
        $template = $this->loadTemplate($context, $chapterNumber, $project);

        return $this->contentDecisionEngine->analyze($project, $chapterNumber, $context, $template);
    }

    private function applyChapterMeta(Project $project, int $chapterNumber): void
    {
        $meta = $this->resolveChapterMeta($project, $chapterNumber);
        foreach (['chapter_title', 'chapter_word_count', 'chapter_type'] as $k) {
            if (array_key_exists($k, $meta) && $meta[$k] !== null) {
                $project->setAttribute($k, $meta[$k]);
            }
        }
    }

    private function resolveChapterMeta(Project $project, int $chapterNumber): array
    {
        $title = null;
        $wordCount = null;

        // Single source of truth for chapter structures:
        // FacultyStructureService already handles custom outlines and faculty-defined chapters.
        $chapters = $this->facultyStructureService->getChapterStructure($project);
        foreach ($chapters as $c) {
            if ((int) ($c['number'] ?? 0) === (int) $chapterNumber) {
                $title = $c['title'] ?? null;
                $wordCount = $c['word_count'] ?? null;
                break;
            }
        }

        $chapterType = $this->inferChapterTypeFromTitle($title, $chapterNumber);

        return [
            'chapter_title' => $title,
            'chapter_word_count' => is_numeric($wordCount) ? (int) $wordCount : null,
            'chapter_type' => $chapterType,
        ];
    }

    private function inferChapterTypeFromTitle(?string $title, int $chapterNumber): string
    {
        $t = strtolower(trim((string) $title));
        if ($t !== '') {
            if (str_contains($t, 'introduction') || str_contains($t, 'background')) {
                return 'introduction';
            }
            if (str_contains($t, 'literature') || (str_contains($t, 'review') && str_contains($t, 'literature'))) {
                return 'literature_review';
            }
            if (str_contains($t, 'method') || str_contains($t, 'design') || str_contains($t, 'materials')) {
                return 'methodology';
            }
            if (str_contains($t, 'result') || str_contains($t, 'findings') || str_contains($t, 'analysis')) {
                return 'results';
            }
            if (str_contains($t, 'discussion')) {
                return 'discussion';
            }
            if (str_contains($t, 'conclusion') || str_contains($t, 'recommendation') || str_contains($t, 'summary')) {
                return 'conclusion';
            }
        }

        // Fallback to numeric defaults only when no title is available.
        return match ($chapterNumber) {
            1 => 'introduction',
            2 => 'literature_review',
            3 => 'methodology',
            4 => 'results',
            5 => 'discussion',
            default => 'general',
        };
    }
}

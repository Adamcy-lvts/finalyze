<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PromptTemplate;
use App\Models\Project;
use App\Services\PromptSystem\ContentDecisionEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class AdminPromptTemplateController extends Controller
{
    private const CONTEXT_TYPES = [
        'faculty',
        'department',
        'course',
        'field_of_study',
        'topic_keyword',
    ];

    public function index()
    {
        $templates = PromptTemplate::query()
            ->orderByDesc('is_active')
            ->orderByDesc('priority')
            ->orderBy('context_type')
            ->orderBy('context_value')
            ->get();

        return Inertia::render('Admin/System/PromptTemplates', [
            'templates' => $templates->map(fn (PromptTemplate $t) => $this->toRow($t)),
            'contextTypes' => self::CONTEXT_TYPES,
            'facultyOptions' => $this->getFacultyOptions(),
        ]);
    }

    public function seedFromCode(Request $request, ContentDecisionEngine $contentDecisionEngine)
    {
        $data = $request->validate([
            'faculty' => ['required', 'string', 'max:100'],
            'chapter_type' => ['nullable', 'string', 'max:50'],
        ]);

        $faculty = (string) $data['faculty'];
        $chapterTypeInput = isset($data['chapter_type']) ? trim((string) $data['chapter_type']) : '';

        $facultyOptions = $this->getFacultyOptions();
        if (! in_array($faculty, $facultyOptions, true)) {
            throw ValidationException::withMessages([
                'faculty' => ['Unknown faculty template.'],
            ]);
        }

        $chapterTypes = $chapterTypeInput === '' || $chapterTypeInput === 'all'
            ? ['introduction', 'literature_review', 'methodology', 'results', 'discussion', 'conclusion']
            : [$chapterTypeInput];

        foreach ($chapterTypes as $ct) {
            if (! in_array($ct, ['introduction', 'literature_review', 'methodology', 'results', 'discussion', 'conclusion'], true)) {
                throw ValidationException::withMessages([
                    'chapter_type' => ['Invalid chapter type.'],
                ]);
            }
        }

        $templateClass = $this->resolveFacultyTemplateClass($faculty);
        $template = new $templateClass;

        $created = 0;
        $skipped = 0;

        foreach ($chapterTypes as $chapterType) {
            $exists = PromptTemplate::query()
                ->where('context_type', 'faculty')
                ->where('context_value', $faculty)
                ->where('chapter_type', $chapterType)
                ->exists();

            if ($exists) {
                $skipped++;
                continue;
            }

            $chapterNumber = $this->chapterTypeToNumber($chapterType);

            $placeholderProject = new Project([
                'topic' => '{{topic}}',
                'faculty' => '{{faculty}}',
                'department' => '{{department}}',
                'course' => '{{course}}',
                'field_of_study' => '{{field_of_study}}',
                'type' => '{{academic_level}}',
                'university' => '{{university}}',
            ]);

            $context = [
                'faculty' => $faculty,
                'department' => null,
                'course' => null,
                'field' => null,
                'project_type' => null,
            ];

            $requirements = $contentDecisionEngine->analyze($placeholderProject, $chapterNumber, $context, $template);
            $chapterPromptTemplate = $template->buildChapterPrompt($placeholderProject, $chapterNumber, $requirements);

            PromptTemplate::create([
                'context_type' => 'faculty',
                'context_value' => $faculty,
                'chapter_type' => $chapterType,
                'parent_template_id' => null,
                'priority' => $template->getPriority(),
                'is_active' => false,
                'system_prompt' => $template->getSystemPrompt(),
                'chapter_prompt_template' => $chapterPromptTemplate,
                'table_requirements' => $template->getTableRequirements($chapterNumber),
                'diagram_requirements' => $template->getDiagramRequirements($chapterNumber),
                'calculation_requirements' => $template->getCalculationRequirements($chapterNumber),
                'code_requirements' => $template->getCodeRequirements($chapterNumber),
                'placeholder_rules' => $template->getPlaceholderRules($chapterNumber),
                'recommended_tools' => $template->getRecommendedTools(),
            ]);

            $created++;
        }

        return back()->with('success', "Created {$created} starter template(s). Skipped {$skipped} existing.");
    }

    public function store(Request $request)
    {
        $validated = $this->validateTemplate($request);

        PromptTemplate::create($validated);

        return back()->with('success', 'Prompt template created.');
    }

    public function update(Request $request, PromptTemplate $template)
    {
        $validated = $this->validateTemplate($request, $template);

        $template->update($validated);

        return back()->with('success', 'Prompt template updated.');
    }

    public function destroy(PromptTemplate $template)
    {
        $template->delete();

        return back()->with('success', 'Prompt template deleted.');
    }

    public function toggleActive(Request $request, PromptTemplate $template)
    {
        $request->validate([
            'is_active' => ['required'],
        ]);

        $template->update(['is_active' => $request->boolean('is_active')]);

        return back()->with('success', 'Template status updated.');
    }

    private function toRow(PromptTemplate $t): array
    {
        return [
            'id' => $t->id,
            'context_type' => $t->context_type,
            'context_value' => $t->context_value,
            'chapter_type' => $t->chapter_type,
            'parent_template_id' => $t->parent_template_id,
            'priority' => $t->priority,
            'is_active' => $t->is_active,
            'system_prompt' => $t->system_prompt,
            'chapter_prompt_template' => $t->chapter_prompt_template,
            'table_requirements' => $t->table_requirements,
            'diagram_requirements' => $t->diagram_requirements,
            'calculation_requirements' => $t->calculation_requirements,
            'code_requirements' => $t->code_requirements,
            'placeholder_rules' => $t->placeholder_rules,
            'recommended_tools' => $t->recommended_tools,
            'mock_data_config' => $t->mock_data_config,
            'citation_requirements' => $t->citation_requirements,
            'formatting_rules' => $t->formatting_rules,
            'created_at' => optional($t->created_at)->toIso8601String(),
            'updated_at' => optional($t->updated_at)->toIso8601String(),
        ];
    }

    private function validateTemplate(Request $request, ?PromptTemplate $template = null): array
    {
        $validated = $request->validate([
            'context_type' => ['required', 'string', Rule::in(self::CONTEXT_TYPES)],
            'context_value' => ['required', 'string', 'max:255'],
            'chapter_type' => ['nullable', 'string', 'max:50'],
            'parent_template_id' => ['nullable', 'integer', 'exists:prompt_templates,id'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
            'system_prompt' => ['nullable', 'string'],
            'chapter_prompt_template' => ['nullable', 'string'],

            // JSON fields: accept array or JSON string (UI uses textarea)
            'table_requirements' => ['nullable'],
            'diagram_requirements' => ['nullable'],
            'calculation_requirements' => ['nullable'],
            'code_requirements' => ['nullable'],
            'placeholder_rules' => ['nullable'],
            'recommended_tools' => ['nullable'],
            'mock_data_config' => ['nullable'],
            'citation_requirements' => ['nullable'],
            'formatting_rules' => ['nullable'],
        ]);

        if ($template && isset($validated['parent_template_id']) && (int) $validated['parent_template_id'] === (int) $template->id) {
            throw ValidationException::withMessages([
                'parent_template_id' => ['Parent template cannot be the same as this template.'],
            ]);
        }

        foreach ([
            'table_requirements',
            'diagram_requirements',
            'calculation_requirements',
            'code_requirements',
            'placeholder_rules',
            'recommended_tools',
            'mock_data_config',
            'citation_requirements',
            'formatting_rules',
        ] as $jsonKey) {
            if (! array_key_exists($jsonKey, $validated)) {
                continue;
            }

            $validated[$jsonKey] = $this->parseJsonField($validated[$jsonKey], $jsonKey);
        }

        foreach ([
            'table_requirements',
            'diagram_requirements',
            'calculation_requirements',
            'code_requirements',
            'placeholder_rules',
        ] as $requirementsKey) {
            if (! array_key_exists($requirementsKey, $validated)) {
                continue;
            }

            $this->validateRequirementShapes($validated[$requirementsKey], $requirementsKey);
        }

        $validated['priority'] = isset($validated['priority']) ? (int) $validated['priority'] : 0;
        $validated['is_active'] = array_key_exists('is_active', $validated) ? (bool) $validated['is_active'] : true;
        $validated['chapter_type'] = $validated['chapter_type'] !== '' ? ($validated['chapter_type'] ?? null) : null;
        $validated['parent_template_id'] = $validated['parent_template_id'] ?? null;

        // Normalize empty strings to null for prompt bodies
        foreach (['system_prompt', 'chapter_prompt_template'] as $key) {
            if (! array_key_exists($key, $validated)) {
                continue;
            }
            $v = $validated[$key];
            $validated[$key] = is_string($v) && trim($v) === '' ? null : $v;
        }

        // Prevent accidental huge payloads
        foreach (['system_prompt', 'chapter_prompt_template'] as $key) {
            if (isset($validated[$key]) && is_string($validated[$key]) && strlen($validated[$key]) > 120_000) {
                throw ValidationException::withMessages([
                    $key => ['Value is too long.'],
                ]);
            }
        }

        // Only allow fillable keys
        return Arr::only($validated, (new PromptTemplate)->getFillable());
    }

    private function validateRequirementShapes(?array $value, string $key): void
    {
        if ($value === null) {
            return;
        }

        $isList = function_exists('array_is_list') ? array_is_list($value) : $this->isListPolyfill($value);
        if (! $isList) {
            return;
        }

        foreach ($value as $idx => $item) {
            if (! is_array($item)) {
                throw ValidationException::withMessages([
                    $key => ["Item #".($idx + 1).' must be an object.'],
                ]);
            }

            $type = $item['type'] ?? null;
            if (! is_string($type) || trim($type) === '') {
                throw ValidationException::withMessages([
                    $key => ["Item #".($idx + 1).' is missing a valid `type` field.'],
                ]);
            }
        }
    }

    private function isListPolyfill(array $value): bool
    {
        $expected = 0;
        foreach (array_keys($value) as $k) {
            if ($k !== $expected) {
                return false;
            }
            $expected++;
        }
        return true;
    }

    private function parseJsonField(mixed $value, string $key): ?array
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value)) {
            throw ValidationException::withMessages([
                $key => ['Must be a JSON array/object, or empty.'],
            ]);
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        $decoded = json_decode($trimmed, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw ValidationException::withMessages([
                $key => ['Invalid JSON.'],
            ]);
        }

        if (! is_array($decoded)) {
            throw ValidationException::withMessages([
                $key => ['JSON must decode to an array/object.'],
            ]);
        }

        return $decoded;
    }

    private function getFacultyOptions(): array
    {
        // Prefer deriving from available code templates under app/Services/PromptSystem/Templates/Faculty.
        // Fallback to a known list (matches PromptRouter mappings) if filesystem glob returns nothing.
        $dir = app_path('Services/PromptSystem/Templates/Faculty');
        $files = glob($dir.'/*Template.php') ?: [];

        $options = [];
        foreach ($files as $file) {
            $name = basename($file, 'Template.php'); // e.g. Engineering
            if ($name === '' || $name === 'Base') {
                continue;
            }
            $options[] = Str::snake($name);
        }

        $options = array_values(array_unique(array_filter($options)));
        sort($options);

        if (! empty($options)) {
            return $options;
        }

        return [
            'engineering',
            'social_science',
            'healthcare',
            'business',
            'science',
            'arts',
            'education',
            'law',
            'agriculture',
        ];
    }

    private function resolveFacultyTemplateClass(string $faculty): string
    {
        $studly = Str::studly($faculty).'Template';
        $class = "App\\Services\\PromptSystem\\Templates\\Faculty\\{$studly}";

        if (! class_exists($class)) {
            throw ValidationException::withMessages([
                'faculty' => ['Faculty template class not found.'],
            ]);
        }

        return $class;
    }

    private function chapterTypeToNumber(string $chapterType): int
    {
        return match ($chapterType) {
            'introduction' => 1,
            'literature_review' => 2,
            'methodology' => 3,
            'results' => 4,
            'discussion' => 5,
            'conclusion' => 6,
            default => 1,
        };
    }
}

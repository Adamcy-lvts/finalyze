<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FacultyChapter;
use App\Models\FacultySection;
use App\Models\FacultyStructure;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class AdminFacultyStructureController extends Controller
{
    private const ACADEMIC_LEVELS = ['all', 'undergraduate', 'masters', 'phd'];
    private const PROJECT_TYPES = ['all', 'thesis', 'project', 'dissertation'];

    public function index()
    {
        $structures = FacultyStructure::query()
            ->orderByDesc('is_active')
            ->orderBy('sort_order')
            ->orderBy('faculty_name')
            ->withCount('chapters')
            ->get();

        return Inertia::render('Admin/System/FacultyStructures/Index', [
            'structures' => $structures->map(fn (FacultyStructure $s) => $this->toStructureRow($s)),
        ]);
    }

    public function show(FacultyStructure $structure)
    {
        $structure->load([
            'chapters.sections' => fn ($q) => $q->orderBy('sort_order'),
        ]);

        return Inertia::render('Admin/System/FacultyStructures/Show', [
            'structure' => $this->toStructureRow($structure),
            'chapters' => $structure->chapters->map(fn (FacultyChapter $c) => $this->toChapterRow($c)),
            'academicLevels' => self::ACADEMIC_LEVELS,
            'projectTypes' => self::PROJECT_TYPES,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateStructure($request);
        FacultyStructure::create($validated);

        return back()->with('success', 'Faculty structure created.');
    }

    public function update(Request $request, FacultyStructure $structure)
    {
        $validated = $this->validateStructure($request, $structure);
        $structure->update($validated);

        return back()->with('success', 'Faculty structure updated.');
    }

    public function destroy(FacultyStructure $structure)
    {
        $structure->delete();

        return back()->with('success', 'Faculty structure deleted.');
    }

    public function toggleActive(Request $request, FacultyStructure $structure)
    {
        $request->validate([
            'is_active' => ['required'],
        ]);

        $structure->update(['is_active' => $request->boolean('is_active')]);

        return back()->with('success', 'Faculty structure status updated.');
    }

    public function storeChapter(Request $request, FacultyStructure $structure)
    {
        $validated = $this->validateChapter($request);
        $validated['faculty_structure_id'] = $structure->id;
        FacultyChapter::create($validated);

        return back()->with('success', 'Chapter created.');
    }

    public function updateChapter(Request $request, FacultyChapter $chapter)
    {
        $validated = $this->validateChapter($request, $chapter);
        $chapter->update($validated);

        return back()->with('success', 'Chapter updated.');
    }

    public function destroyChapter(FacultyChapter $chapter)
    {
        $chapter->delete();

        return back()->with('success', 'Chapter deleted.');
    }

    public function storeSection(Request $request, FacultyChapter $chapter)
    {
        $validated = $this->validateSection($request);
        $validated['faculty_chapter_id'] = $chapter->id;
        FacultySection::create($validated);

        return back()->with('success', 'Section created.');
    }

    public function updateSection(Request $request, FacultySection $section)
    {
        $validated = $this->validateSection($request, $section);
        $section->update($validated);

        return back()->with('success', 'Section updated.');
    }

    public function destroySection(FacultySection $section)
    {
        $section->delete();

        return back()->with('success', 'Section deleted.');
    }

    private function toStructureRow(FacultyStructure $s): array
    {
        return [
            'id' => $s->id,
            'faculty_name' => $s->faculty_name,
            'faculty_slug' => $s->faculty_slug,
            'description' => $s->description,
            'academic_levels' => $s->academic_levels,
            'default_structure' => $s->default_structure,
            'chapter_templates' => $s->chapter_templates,
            'guidance_templates' => $s->guidance_templates,
            'terminology' => $s->terminology,
            'is_active' => (bool) $s->is_active,
            'sort_order' => $s->sort_order,
            'chapters_count' => $s->chapters_count ?? null,
            'created_at' => optional($s->created_at)->toIso8601String(),
            'updated_at' => optional($s->updated_at)->toIso8601String(),
        ];
    }

    private function toChapterRow(FacultyChapter $c): array
    {
        return [
            'id' => $c->id,
            'faculty_structure_id' => $c->faculty_structure_id,
            'academic_level' => $c->academic_level,
            'project_type' => $c->project_type,
            'chapter_number' => $c->chapter_number,
            'chapter_title' => $c->chapter_title,
            'description' => $c->description,
            'target_word_count' => $c->target_word_count,
            'completion_threshold' => $c->completion_threshold,
            'is_required' => $c->is_required,
            'sort_order' => $c->sort_order,
            'sections' => $c->sections->map(fn (FacultySection $s) => [
                'id' => $s->id,
                'faculty_chapter_id' => $s->faculty_chapter_id,
                'section_number' => $s->section_number,
                'section_title' => $s->section_title,
                'description' => $s->description,
                'writing_guidance' => $s->writing_guidance,
                'tips' => $s->tips,
                'target_word_count' => $s->target_word_count,
                'is_required' => $s->is_required,
                'sort_order' => $s->sort_order,
            ])->toArray(),
        ];
    }

    private function validateStructure(Request $request, ?FacultyStructure $structure = null): array
    {
        $data = $request->validate([
            'faculty_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('faculty_structures', 'faculty_name')->ignore($structure?->id),
            ],
            'faculty_slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9-]+$/',
                Rule::unique('faculty_structures', 'faculty_slug')->ignore($structure?->id),
            ],
            'description' => ['nullable', 'string', 'max:5000'],
            'academic_levels' => ['required'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000'],

            // Optional JSON blobs edited via textarea
            'default_structure' => ['nullable'],
            'chapter_templates' => ['nullable'],
            'guidance_templates' => ['nullable'],
            'terminology' => ['nullable'],
        ]);

        $data['academic_levels'] = $this->parseAcademicLevels($data['academic_levels']);
        foreach (['default_structure', 'chapter_templates', 'guidance_templates', 'terminology'] as $k) {
            if (array_key_exists($k, $data)) {
                $data[$k] = $this->parseJsonField($data[$k], $k) ?? [];
            }
        }

        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['is_active'] = array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true;

        // Required by schema, default to empty arrays if missing
        $data['default_structure'] = $data['default_structure'] ?? ($structure?->default_structure ?? []);
        $data['chapter_templates'] = $data['chapter_templates'] ?? ($structure?->chapter_templates ?? []);
        $data['guidance_templates'] = $data['guidance_templates'] ?? ($structure?->guidance_templates ?? []);
        $data['terminology'] = $data['terminology'] ?? ($structure?->terminology ?? []);

        return Arr::only($data, (new FacultyStructure)->getFillable());
    }

    private function validateChapter(Request $request, ?FacultyChapter $chapter = null): array
    {
        $data = $request->validate([
            'academic_level' => ['required', 'string', Rule::in(self::ACADEMIC_LEVELS)],
            'project_type' => ['required', 'string', Rule::in(self::PROJECT_TYPES)],
            'chapter_number' => ['required', 'integer', 'min:1', 'max:30'],
            'chapter_title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'target_word_count' => ['nullable', 'integer', 'min:100', 'max:200000'],
            'completion_threshold' => ['nullable', 'integer', 'min:1', 'max:100'],
            'is_required' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000'],
        ]);

        $data['target_word_count'] = $data['target_word_count'] ?? 3000;
        $data['completion_threshold'] = $data['completion_threshold'] ?? 80;
        $data['sort_order'] = $data['sort_order'] ?? $data['chapter_number'];
        $data['is_required'] = array_key_exists('is_required', $data) ? (bool) $data['is_required'] : true;

        return Arr::only($data, (new FacultyChapter)->getFillable());
    }

    private function validateSection(Request $request, ?FacultySection $section = null): array
    {
        $data = $request->validate([
            'section_number' => ['required', 'string', 'max:20'],
            'section_title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'writing_guidance' => ['nullable', 'string', 'max:20000'],
            'tips' => ['nullable'],
            'target_word_count' => ['nullable', 'integer', 'min:50', 'max:50000'],
            'is_required' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000'],
        ]);

        $data['tips'] = $this->parseTips($data['tips']);
        $data['target_word_count'] = $data['target_word_count'] ?? 500;
        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['is_required'] = array_key_exists('is_required', $data) ? (bool) $data['is_required'] : true;

        return Arr::only($data, (new FacultySection)->getFillable());
    }

    private function parseAcademicLevels(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_unique(array_filter(array_map('strval', $value))));
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return ['undergraduate'];
            }
            $decoded = json_decode($trimmed, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return array_values(array_unique(array_filter(array_map('strval', $decoded))));
            }
            $parts = preg_split('/\s*,\s*/', $trimmed) ?: [];
            return array_values(array_unique(array_filter(array_map('strval', $parts))));
        }

        return ['undergraduate'];
    }

    private function parseTips(mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value)) {
            throw ValidationException::withMessages([
                'tips' => ['Must be JSON array, newline list, or empty.'],
            ]);
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        $decoded = json_decode($trimmed, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        $lines = preg_split('/\r?\n/', $trimmed) ?: [];
        $lines = array_values(array_filter(array_map(fn ($l) => trim((string) $l), $lines)));
        return $lines === [] ? null : $lines;
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
                $key => ['Must be a JSON object/array, or empty.'],
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
}

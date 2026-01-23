<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProjectCategory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class AdminProjectCategoryController extends Controller
{
    private const ACADEMIC_LEVELS = ['undergraduate', 'postgraduate'];

    public function index()
    {
        $categories = ProjectCategory::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return Inertia::render('Admin/System/ProjectCategories/Index', [
            'categories' => $categories->map(fn (ProjectCategory $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'academic_levels' => $category->academic_levels ?? [],
                'description' => $category->description,
                'default_chapter_count' => $category->default_chapter_count,
                'chapter_structure' => $category->chapter_structure ?? [],
                'target_word_count' => $category->target_word_count,
                'target_duration' => $category->target_duration,
                'is_active' => $category->is_active,
                'sort_order' => $category->sort_order,
                'created_at' => $category->created_at?->toISOString(),
            ]),
            'academicLevels' => self::ACADEMIC_LEVELS,
            'stats' => [
                'total' => $categories->count(),
                'active' => $categories->where('is_active', true)->count(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateCategory($request);

        ProjectCategory::create($data);

        return back()->with('success', 'Project category created successfully.');
    }

    public function update(Request $request, ProjectCategory $category)
    {
        $data = $this->validateCategory($request, $category);

        $category->update($data);

        return back()->with('success', 'Project category updated.');
    }

    public function destroy(ProjectCategory $category)
    {
        $category->delete();

        return back()->with('success', 'Project category removed.');
    }

    public function toggleActive(Request $request, ProjectCategory $category)
    {
        $data = $request->validate([
            'is_active' => 'required|boolean',
        ]);

        $category->update(['is_active' => $data['is_active']]);

        return back()->with('success', 'Project category availability updated.');
    }

    private function validateCategory(Request $request, ?ProjectCategory $category = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9-]+$/',
                Rule::unique('project_categories', 'slug')->ignore($category?->id),
            ],
            'academic_levels' => ['required', 'array', 'min:1'],
            'academic_levels.*' => ['string', Rule::in(self::ACADEMIC_LEVELS)],
            'description' => ['required', 'string', 'max:2000'],
            'default_chapter_count' => ['required', 'integer', 'min:1', 'max:20'],
            'chapter_structure' => ['nullable'],
            'target_word_count' => ['nullable', 'integer', 'min:0'],
            'target_duration' => ['nullable', 'string', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $validated['chapter_structure'] = $this->parseChapterStructure($request->input('chapter_structure'));
        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated['is_active'] = array_key_exists('is_active', $validated) ? (bool) $validated['is_active'] : true;

        return $validated;
    }

    private function parseChapterStructure($value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value)) {
            return [];
        }

        $decoded = json_decode($value, true);
        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
            throw ValidationException::withMessages([
                'chapter_structure' => 'Chapter structure must be valid JSON.',
            ]);
        }

        return $decoded;
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Faculty;
use App\Models\FacultyStructure;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class AdminFacultyController extends Controller
{
    public function index()
    {
        $faculties = Faculty::query()
            ->with('structure:id,faculty_name')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $structures = FacultyStructure::query()
            ->orderBy('faculty_name')
            ->get(['id', 'faculty_name']);

        return Inertia::render('Admin/System/Faculties/Index', [
            'faculties' => $faculties->map(fn (Faculty $faculty) => [
                'id' => $faculty->id,
                'name' => $faculty->name,
                'slug' => $faculty->slug,
                'description' => $faculty->description,
                'faculty_structure_id' => $faculty->faculty_structure_id,
                'faculty_structure_name' => $faculty->structure?->faculty_name,
                'sort_order' => $faculty->sort_order,
                'is_active' => $faculty->is_active,
                'created_at' => $faculty->created_at?->toISOString(),
            ]),
            'structures' => $structures->map(fn (FacultyStructure $structure) => [
                'id' => $structure->id,
                'faculty_name' => $structure->faculty_name,
            ]),
            'stats' => [
                'total' => $faculties->count(),
                'active' => $faculties->where('is_active', true)->count(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateFaculty($request);

        Faculty::create($data);

        return back()->with('success', 'Faculty created successfully.');
    }

    public function update(Request $request, Faculty $faculty)
    {
        $data = $this->validateFaculty($request, $faculty);

        $faculty->update($data);

        return back()->with('success', 'Faculty updated.');
    }

    public function destroy(Faculty $faculty)
    {
        $faculty->delete();

        return back()->with('success', 'Faculty removed.');
    }

    public function toggleActive(Request $request, Faculty $faculty)
    {
        $data = $request->validate([
            'is_active' => 'required|boolean',
        ]);

        $faculty->update(['is_active' => $data['is_active']]);

        return back()->with('success', 'Faculty availability updated.');
    }

    private function validateFaculty(Request $request, ?Faculty $faculty = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9-]+$/',
                Rule::unique('faculties', 'slug')->ignore($faculty?->id),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'faculty_structure_id' => ['nullable', 'exists:faculty_structures,id'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated['is_active'] = array_key_exists('is_active', $validated) ? (bool) $validated['is_active'] : true;

        return $validated;
    }
}

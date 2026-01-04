<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Faculty;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class AdminDepartmentController extends Controller
{
    public function index()
    {
        $hasPivot = Schema::hasTable('department_faculty');
        $departmentsQuery = Department::query()->with('faculty:id,name');
        if ($hasPivot) {
            // Load faculties with pivot data to get is_primary flag
            $departmentsQuery->with(['faculties' => function ($query) {
                $query->select('faculties.id', 'faculties.name');
            }]);
        }

        $departments = $departmentsQuery
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $faculties = Faculty::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Admin/System/Departments/Index', [
            'departments' => $departments->map(function (Department $department) use ($hasPivot) {
                // Get faculty IDs from pivot table or fallback to legacy faculty_id
                $pivotFacultyIds = $hasPivot ? $department->faculties->pluck('id')->values()->all() : [];
                $facultyIds = count($pivotFacultyIds) > 0
                    ? $pivotFacultyIds
                    : array_values(array_filter([$department->faculty_id]));

                // Get primary faculty ID
                $primaryFacultyId = null;
                if ($hasPivot) {
                    $primaryFaculty = $department->faculties->firstWhere('pivot.is_primary', true);
                    $primaryFacultyId = $primaryFaculty?->id ?? $department->faculty_id;
                } else {
                    $primaryFacultyId = $department->faculty_id;
                }

                return [
                    'id' => $department->id,
                    'name' => $department->name,
                    'slug' => $department->slug,
                    'code' => $department->code,
                    'description' => $department->description,
                    'faculty_id' => $department->faculty_id,
                    'faculty_name' => $department->faculty?->name,
                    'faculty_ids' => $facultyIds,
                    'primary_faculty_id' => $primaryFacultyId,
                    'sort_order' => $department->sort_order,
                    'is_active' => $department->is_active,
                    'created_at' => $department->created_at?->toISOString(),
                ];
            }),
            'faculties' => $faculties->map(fn (Faculty $faculty) => [
                'id' => $faculty->id,
                'name' => $faculty->name,
            ]),
            'stats' => [
                'total' => $departments->count(),
                'active' => $departments->where('is_active', true)->count(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateDepartment($request);

        // Ensure faculty IDs are integers for proper comparison
        $facultyIds = array_map('intval', $data['faculty_ids']);
        $primaryFacultyId = isset($data['primary_faculty_id']) ? (int) $data['primary_faculty_id'] : ($facultyIds[0] ?? null);

        $department = Department::create(array_merge($data, [
            'faculty_id' => $primaryFacultyId,
        ]));

        if ($primaryFacultyId && Schema::hasTable('department_faculty')) {
            $department->syncFaculties($facultyIds, $primaryFacultyId);
        }

        return back()->with('success', 'Department created successfully.');
    }

    public function update(Request $request, Department $department)
    {
        // DEBUG: Log what's being received
        \Log::info('Department update - Raw request', [
            'faculty_ids' => $request->input('faculty_ids'),
            'primary_faculty_id' => $request->input('primary_faculty_id'),
        ]);

        $data = $this->validateDepartment($request, $department);

        // Ensure faculty IDs are integers for proper comparison
        $facultyIds = array_map('intval', $data['faculty_ids']);
        $primaryFacultyId = isset($data['primary_faculty_id']) ? (int) $data['primary_faculty_id'] : ($facultyIds[0] ?? null);

        // DEBUG: Log after processing
        \Log::info('Department update - After processing', [
            'faculty_ids' => $facultyIds,
            'primary_faculty_id' => $primaryFacultyId,
            'department_id' => $department->id,
        ]);

        $department->update(array_merge($data, [
            'faculty_id' => $primaryFacultyId,
        ]));

        if ($primaryFacultyId && Schema::hasTable('department_faculty')) {
            $department->syncFaculties($facultyIds, $primaryFacultyId);
        }

        return back()->with('success', 'Department updated.');
    }

    public function destroy(Department $department)
    {
        $department->delete();

        return back()->with('success', 'Department removed.');
    }

    public function toggleActive(Request $request, Department $department)
    {
        $data = $request->validate([
            'is_active' => 'required|boolean',
        ]);

        $department->update(['is_active' => $data['is_active']]);

        return back()->with('success', 'Department availability updated.');
    }

    private function validateDepartment(Request $request, ?Department $department = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9-]+$/',
                Rule::unique('departments', 'slug')->ignore($department?->id),
            ],
            'code' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:2000'],
            'faculty_ids' => ['required', 'array', 'min:1'],
            'faculty_ids.*' => ['integer', 'exists:faculties,id'],
            'primary_faculty_id' => ['nullable', 'integer', 'exists:faculties,id'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated['is_active'] = array_key_exists('is_active', $validated) ? (bool) $validated['is_active'] : true;

        // Ensure faculty_ids are integers for consistent comparison
        $validated['faculty_ids'] = array_map('intval', $validated['faculty_ids']);
        $primaryFacultyId = isset($validated['primary_faculty_id']) ? (int) $validated['primary_faculty_id'] : null;

        if ($primaryFacultyId && ! in_array($primaryFacultyId, $validated['faculty_ids'], true)) {
            $validated['primary_faculty_id'] = $validated['faculty_ids'][0];
        } else {
            $validated['primary_faculty_id'] = $primaryFacultyId;
        }

        return $validated;
    }
}

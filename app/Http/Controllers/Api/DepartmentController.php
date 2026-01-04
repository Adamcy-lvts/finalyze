<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Faculty;
use Illuminate\Http\JsonResponse;

class DepartmentController extends Controller
{
    /**
     * Get all active departments
     */
    public function index(): JsonResponse
    {
        $departments = Department::query()
            ->active()
            ->with('faculty:id,name,slug')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get([
                'id',
                'faculty_id',
                'name',
                'slug',
                'code',
                'description',
            ]);

        return response()->json([
            'departments' => $departments,
        ]);
    }

    /**
     * Get departments by faculty
     * Supports both legacy faculty_id and new pivot table relationships
     */
    public function byFaculty(Faculty $faculty): JsonResponse
    {
        // Use the new scope that checks both legacy and pivot relationships
        $departments = Department::query()
            ->byFaculty($faculty->id)
            ->active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get([
                'id',
                'faculty_id',
                'name',
                'slug',
                'code',
                'description',
            ]);

        return response()->json([
            'departments' => $departments,
        ]);
    }
}

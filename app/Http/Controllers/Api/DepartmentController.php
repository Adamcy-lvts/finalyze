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
     */
    public function byFaculty(Faculty $faculty): JsonResponse
    {
        $departments = Department::query()
            ->where('faculty_id', $faculty->id)
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

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Faculty;
use Illuminate\Http\JsonResponse;

class FacultyController extends Controller
{
    /**
     * Get all active faculties
     */
    public function index(): JsonResponse
    {
        $faculties = Faculty::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'slug',
                'description',
                'faculty_structure_id',
            ]);

        return response()->json([
            'faculties' => $faculties,
        ]);
    }
}

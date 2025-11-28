<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\University;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UniversityController extends Controller
{
    /**
     * Get all active universities
     */
    public function index(Request $request): JsonResponse
    {
        $query = University::query()->active()->orderBy('sort_order')->orderBy('name');

        // Filter by type if provided
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        $universities = $query->get([
            'id',
            'name',
            'short_name',
            'slug',
            'type',
            'location',
            'state',
        ]);

        return response()->json([
            'universities' => $universities,
        ]);
    }
}

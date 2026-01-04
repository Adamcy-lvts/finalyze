<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\University;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class AdminUniversityController extends Controller
{
    public function index()
    {
        $universities = University::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return Inertia::render('Admin/System/Universities/Index', [
            'universities' => $universities->map(fn (University $university) => [
                'id' => $university->id,
                'name' => $university->name,
                'short_name' => $university->short_name,
                'slug' => $university->slug,
                'type' => $university->type,
                'location' => $university->location,
                'state' => $university->state,
                'country' => $university->country,
                'website' => $university->website,
                'description' => $university->description,
                'sort_order' => $university->sort_order,
                'is_active' => $university->is_active,
                'created_at' => $university->created_at?->toISOString(),
            ]),
            'stats' => [
                'total' => $universities->count(),
                'active' => $universities->where('is_active', true)->count(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateUniversity($request);

        University::create($data);

        return back()->with('success', 'University created successfully.');
    }

    public function update(Request $request, University $university)
    {
        $data = $this->validateUniversity($request, $university);

        $university->update($data);

        return back()->with('success', 'University updated.');
    }

    public function destroy(University $university)
    {
        $university->delete();

        return back()->with('success', 'University removed.');
    }

    public function toggleActive(Request $request, University $university)
    {
        $data = $request->validate([
            'is_active' => 'required|boolean',
        ]);

        $university->update(['is_active' => $data['is_active']]);

        return back()->with('success', 'University availability updated.');
    }

    private function validateUniversity(Request $request, ?University $university = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'short_name' => ['nullable', 'string', 'max:50'],
            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9-]+$/',
                Rule::unique('universities', 'slug')->ignore($university?->id),
            ],
            'type' => ['required', Rule::in(['federal', 'state', 'private', 'other'])],
            'location' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $validated['country'] = $validated['country'] ?? $university?->country ?? 'Nigeria';
        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated['is_active'] = array_key_exists('is_active', $validated) ? (bool) $validated['is_active'] : true;

        return $validated;
    }
}

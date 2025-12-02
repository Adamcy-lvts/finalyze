<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WordPackage;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class AdminPackageController extends Controller
{
    public function index()
    {
        $packages = WordPackage::orderBy('sort_order')->orderBy('price')->get();

        return Inertia::render('Admin/Packages/Index', [
            'packages' => $packages->map(fn ($pkg) => [
                'id' => $pkg->id,
                'name' => $pkg->name,
                'slug' => $pkg->slug,
                'type' => $pkg->type,
                'tier' => $pkg->tier,
                'words' => $pkg->words,
                'formatted_words' => $pkg->formatted_words,
                'price' => $pkg->price_in_naira,
                'formatted_price' => $pkg->formatted_price,
                'currency' => $pkg->currency,
                'description' => $pkg->description,
                'features' => $pkg->features ?? [],
                'sort_order' => $pkg->sort_order,
                'is_active' => $pkg->is_active,
                'is_popular' => $pkg->is_popular,
                'created_at' => $pkg->created_at,
            ]),
            'stats' => [
                'total' => $packages->count(),
                'active' => $packages->where('is_active', true)->count(),
                'projects' => $packages->where('type', WordPackage::TYPE_PROJECT)->count(),
                'topups' => $packages->where('type', WordPackage::TYPE_TOPUP)->count(),
            ],
            'defaults' => [
                'currency' => 'NGN',
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validatePackage($request);

        WordPackage::create($data);

        return back()->with('success', 'Package created successfully.');
    }

    public function update(Request $request, WordPackage $package)
    {
        $data = $this->validatePackage($request, $package);

        $package->update($data);

        return back()->with('success', 'Package updated.');
    }

    public function destroy(WordPackage $package)
    {
        $package->delete();

        return back()->with('success', 'Package removed.');
    }

    public function toggleActive(Request $request, WordPackage $package)
    {
        $data = $request->validate([
            'is_active' => 'required|boolean',
        ]);

        $package->update(['is_active' => $data['is_active']]);

        return back()->with('success', 'Package availability updated.');
    }

    public function togglePopular(Request $request, WordPackage $package)
    {
        $data = $request->validate([
            'is_popular' => 'required|boolean',
        ]);

        $package->update(['is_popular' => $data['is_popular']]);

        return back()->with('success', 'Package highlight updated.');
    }

    /**
     * Validate and normalize package data.
     */
    private function validatePackage(Request $request, ?WordPackage $package = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9-]+$/',
                Rule::unique('word_packages', 'slug')->ignore($package?->id),
            ],
            'type' => ['required', Rule::in([WordPackage::TYPE_PROJECT, WordPackage::TYPE_TOPUP])],
            'tier' => ['nullable', 'string', 'max:255', 'required_if:type,'.WordPackage::TYPE_PROJECT],
            'words' => ['required', 'integer', 'min:100'],
            'price' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'description' => ['nullable', 'string', 'max:2000'],
            'features' => ['nullable', 'array'],
            'features.*' => ['string', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'is_popular' => ['sometimes', 'boolean'],
            'features_text' => ['sometimes', 'nullable', 'string'],
        ]);

        $validated['features'] = $validated['features'] ?? $this->extractFeatures($validated['features_text'] ?? null);
        unset($validated['features_text']);

        $validated['tier'] = $validated['type'] === WordPackage::TYPE_PROJECT ? ($validated['tier'] ?? null) : null;
        $validated['price'] = (int) round($validated['price'] * 100); // store as kobo
        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated['is_active'] = array_key_exists('is_active', $validated) ? (bool) $validated['is_active'] : true;
        $validated['is_popular'] = array_key_exists('is_popular', $validated) ? (bool) $validated['is_popular'] : false;

        return $validated;
    }

    /**
     * Convert newline-separated features into an array.
     */
    private function extractFeatures(?string $raw): array
    {
        if (! $raw) {
            return [];
        }

        return collect(preg_split('/\r?\n/', $raw))
            ->map(fn ($line) => trim($line))
            ->filter()
            ->values()
            ->all();
    }
}

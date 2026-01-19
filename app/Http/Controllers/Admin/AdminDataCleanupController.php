<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\DataCleanupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminDataCleanupController extends Controller
{
    public function index(DataCleanupService $cleanupService)
    {
        $tables = $cleanupService->tableSummaries();
        $lockedTables = $cleanupService->lockedTables(array_column($tables, 'name'));

        return Inertia::render('Admin/System/Cleanup', [
            'tables' => $tables,
            'lockedTables' => $lockedTables,
            'userStats' => $cleanupService->userStats(),
            'confirmPhrase' => config('admin_cleanup.confirm_phrase', 'DELETE PRODUCTION DATA'),
        ]);
    }

    public function run(Request $request, DataCleanupService $cleanupService): RedirectResponse
    {
        $data = $request->validate([
            'tables' => ['required', 'array', 'min:1'],
            'tables.*' => ['string'],
            'confirm_phrase' => ['required', 'string'],
        ]);

        $confirmPhrase = config('admin_cleanup.confirm_phrase', 'DELETE PRODUCTION DATA');
        if ($data['confirm_phrase'] !== $confirmPhrase) {
            return back()->withErrors([
                'confirm_phrase' => 'Confirmation phrase does not match.',
            ]);
        }

        $deletedCounts = $cleanupService->purge($data['tables'], $request->user());
        $totalDeleted = array_sum($deletedCounts);

        return back()->with('success', "Cleanup complete. Deleted {$totalDeleted} rows.");
    }
}

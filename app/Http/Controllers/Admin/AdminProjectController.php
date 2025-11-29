<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Inertia\Inertia;

class AdminProjectController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Projects/Index');
    }

    public function show(Project $project)
    {
        return Inertia::render('Admin/Projects/Show', ['projectId' => $project->id]);
    }

    public function destroy(Project $project)
    {
        return response()->json(['status' => 'ok']);
    }

    public function export(Project $project)
    {
        return response()->json(['status' => 'ok']);
    }
}

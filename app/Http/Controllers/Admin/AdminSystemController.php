<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Inertia\Inertia;

class AdminSystemController extends Controller
{
    public function features()
    {
        return Inertia::render('Admin/System/Features');
    }

    public function updateFeature($flag)
    {
        return response()->json(['status' => 'ok']);
    }

    public function settings()
    {
        return Inertia::render('Admin/System/Settings');
    }

    public function updateSettings()
    {
        return response()->json(['status' => 'ok']);
    }

    public function clearCache()
    {
        return response()->json(['status' => 'ok']);
    }
}

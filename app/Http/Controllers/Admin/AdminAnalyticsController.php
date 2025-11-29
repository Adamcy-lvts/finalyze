<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Inertia\Inertia;

class AdminAnalyticsController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Analytics/Index');
    }

    public function users()
    {
        return Inertia::render('Admin/Analytics/Users');
    }

    public function revenue()
    {
        return Inertia::render('Admin/Analytics/Revenue');
    }

    public function usage()
    {
        return Inertia::render('Admin/Analytics/Usage');
    }

    public function export()
    {
        return response()->json(['status' => 'ok']);
    }
}

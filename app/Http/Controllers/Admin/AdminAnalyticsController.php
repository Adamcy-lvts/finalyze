<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Project;
use App\Models\User;
use Carbon\Carbon;
use Inertia\Inertia;

class AdminAnalyticsController extends Controller
{
    public function index()
    {
        $today = Carbon::today();
        $metrics = [
            'users' => [
                'total' => User::count(),
                'new_today' => User::whereDate('created_at', $today)->count(),
            ],
            'projects' => [
                'total' => Project::count(),
                'new_today' => Project::whereDate('created_at', $today)->count(),
            ],
            'revenue' => [
                'total' => Payment::successful()->sum('amount') / 100,
                'today' => Payment::successful()->whereDate('paid_at', $today)->sum('amount') / 100,
            ],
        ];

        return Inertia::render('Admin/Analytics/Index', [
            'metrics' => $metrics,
        ]);
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

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AIUsageDaily;
use App\Models\Payment;
use App\Models\Project;
use App\Models\User;
use App\Models\WordTransaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Inertia\Inertia;

class AdminAnalyticsController extends Controller
{
    public function index()
    {
        $today = Carbon::today();
        $thirtyDaysAgo = Carbon::today()->subDays(30);
        $sevenDaysAgo = Carbon::today()->subDays(7);

        // Basic counts
        $totalUsers = User::count();
        $totalProjects = Project::count();
        $totalRevenue = Payment::successful()->sum('amount') / 100;
        $totalWordsUsed = WordTransaction::where('type', WordTransaction::TYPE_USAGE)->sum(DB::raw('ABS(words)'));

        // Previous period for trends
        $prevThirtyDaysStart = Carbon::today()->subDays(60);
        $prevThirtyDaysEnd = Carbon::today()->subDays(31);

        $usersLast30 = User::where('created_at', '>=', $thirtyDaysAgo)->count();
        $usersPrev30 = User::whereBetween('created_at', [$prevThirtyDaysStart, $prevThirtyDaysEnd])->count();
        $usersTrend = $usersPrev30 > 0 ? round((($usersLast30 - $usersPrev30) / $usersPrev30) * 100) : 0;

        $revenueLast30 = Payment::successful()->where('paid_at', '>=', $thirtyDaysAgo)->sum('amount') / 100;
        $revenuePrev30 = Payment::successful()->whereBetween('paid_at', [$prevThirtyDaysStart, $prevThirtyDaysEnd])->sum('amount') / 100;
        $revenueTrend = $revenuePrev30 > 0 ? round((($revenueLast30 - $revenuePrev30) / $revenuePrev30) * 100) : 0;

        $projectsLast30 = Project::where('created_at', '>=', $thirtyDaysAgo)->count();
        $projectsPrev30 = Project::whereBetween('created_at', [$prevThirtyDaysStart, $prevThirtyDaysEnd])->count();
        $projectsTrend = $projectsPrev30 > 0 ? round((($projectsLast30 - $projectsPrev30) / $projectsPrev30) * 100) : 0;

        // Revenue trend for chart (daily for last 30 days)
        $revenueChart = Payment::successful()
            ->where('paid_at', '>=', $thirtyDaysAgo)
            ->selectRaw('DATE(paid_at) as date, SUM(amount) / 100 as amount')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => [
                'date' => $row->date,
                'amount' => (float) $row->amount,
            ])
            ->toArray();

        // User signups for chart (daily for last 7 days)
        $signupsChart = User::where('created_at', '>=', $sevenDaysAgo)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => [
                'label' => Carbon::parse($row->date)->format('D'),
                'date' => $row->date,
                'newUsers' => (int) $row->count,
            ])
            ->toArray();

        // Queue health
        $queuePending = 0;
        $queueFailed = 0;
        try {
            $queuePending = DB::table('jobs')->count();
            $queueFailed = DB::table('failed_jobs')->count();
        } catch (\Exception $e) {
            // Tables may not exist
        }

        // AI usage stats
        $aiTokensToday = AIUsageDaily::where('date', $today)->sum('total_tokens');

        return Inertia::render('Admin/Analytics/Index', [
            'stats' => [
                'users' => [
                    'total' => $totalUsers,
                    'trend' => $usersTrend,
                ],
                'revenue' => [
                    'total' => $totalRevenue,
                    'last30' => $revenueLast30,
                    'trend' => $revenueTrend,
                ],
                'projects' => [
                    'total' => $totalProjects,
                    'trend' => $projectsTrend,
                ],
                'wordsUsed' => [
                    'total' => $totalWordsUsed,
                ],
            ],
            'revenueChart' => $revenueChart,
            'signupsChart' => $signupsChart,
            'systemHealth' => [
                'queue' => [
                    'pending' => $queuePending,
                    'failed' => $queueFailed,
                    'status' => $queueFailed > 10 ? 'warning' : 'healthy',
                ],
                'aiTokensToday' => $aiTokensToday,
            ],
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

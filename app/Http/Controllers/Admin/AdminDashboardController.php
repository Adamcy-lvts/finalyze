<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Payment;
use App\Models\Project;
use App\Models\RegistrationInvite;
use App\Models\User;
use App\Models\WordTransaction;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Inertia\Inertia;

class AdminDashboardController extends Controller
{
    public function index()
    {
        $totalUsers = User::count();
        $newUsersToday = User::whereDate('created_at', Carbon::today())->count();

        $totalRevenueKobo = Payment::successful()->sum('amount');
        $todayRevenueKobo = Payment::successful()->whereDate('paid_at', Carbon::today())->sum('amount');

        $totalProjects = Project::count();
        $projectsToday = Project::whereDate('created_at', Carbon::today())->count();

        $wordsGenerated = abs(WordTransaction::where('type', WordTransaction::TYPE_USAGE)->sum('words'));
        $wordsGeneratedToday = abs(WordTransaction::where('type', WordTransaction::TYPE_USAGE)->whereDate('created_at', Carbon::today())->sum('words'));

        $activityLogs = ActivityLog::query()
            ->with('causer')
            ->latest()
            ->take(200)
            ->get()
            ->groupBy(fn (ActivityLog $a) => implode('|', [
                $a->type,
                $a->message,
                $a->route ?? '',
                $a->method ?? '',
            ]))
            ->map(function ($group) {
                /** @var ActivityLog $latest */
                $latest = $group->sortByDesc('created_at')->first();

                return [
                    'type' => $latest->type,
                    'message' => $latest->message,
                    'time' => $latest->created_at->diffForHumans(),
                    'created_at' => $latest->created_at->toDateTimeString(),
                    'count' => $group->count(),
                    'actor' => $latest->causer?->name ?? $latest->causer?->email,
                    'route' => $latest->route,
                    'method' => $latest->method,
                    'status_code' => $latest->status_code,
                    'duration_ms' => $latest->duration_ms,
                    'sort_at' => $latest->created_at->timestamp,
                ];
            })
            ->sortByDesc('sort_at')
            ->values()
            ->take(15)
            ->map(fn (array $item) => Arr::except($item, ['sort_at']))
            ->toArray();

        // Backward-compatible fallback if no logs exist yet.
        if (empty($activityLogs)) {
            $activityLogs = [
                ...Payment::with('user')
                    ->latest()
                    ->take(5)
                    ->get()
                    ->map(fn ($p) => [
                        'type' => 'payment.success',
                        'message' => "Payment of ₦".number_format($p->amount / 100, 0)." by ".$p->user?->email,
                        'time' => $p->created_at->diffForHumans(),
                        'created_at' => $p->created_at->toDateTimeString(),
                        'count' => 1,
                        'actor' => $p->user?->name ?? $p->user?->email,
                        'route' => null,
                        'method' => null,
                        'status_code' => null,
                        'duration_ms' => null,
                    ])->toArray(),
                ...User::latest()->take(5)->get()->map(fn ($u) => [
                    'type' => 'user.registered',
                    'message' => "New signup: {$u->email}",
                    'time' => $u->created_at->diffForHumans(),
                    'created_at' => $u->created_at->toDateTimeString(),
                    'count' => 1,
                    'actor' => $u->name ?? $u->email,
                    'route' => null,
                    'method' => null,
                    'status_code' => null,
                    'duration_ms' => null,
                ])->toArray(),
            ];
        }

        $invites = RegistrationInvite::query()
            ->latest()
            ->take(25)
            ->get()
            ->map(fn (RegistrationInvite $invite) => [
                'id' => $invite->id,
                'code' => $invite->code,
                'link' => route('invite.show', ['code' => $invite->code]),
                'uses' => $invite->uses,
                'max_uses' => $invite->max_uses,
                'status' => $invite->status(),
                'expires_at' => $invite->expires_at?->toDateTimeString(),
                'revoked_at' => $invite->revoked_at?->toDateTimeString(),
                'created_at' => $invite->created_at?->toDateTimeString(),
            ]);

        $revenueDaily = $this->buildDailyRevenueSeries(30);
        $revenueMonthly = $this->buildMonthlyRevenueSeries(12);
        $registrationsDaily = $this->buildDailyRegistrationsSeries(30);
        $registrationsMonthly = $this->buildMonthlyRegistrationsSeries(12);

        return Inertia::render('Admin/Dashboard', [
            'inviteOnlyEnabled' => (bool) config('registration.invite_only', true),
            'stats' => [
                'users' => ['total' => $totalUsers, 'today' => $newUsersToday],
                'revenue' => ['total' => $totalRevenueKobo / 100, 'today' => $todayRevenueKobo / 100],
                'projects' => ['total' => $totalProjects, 'today' => $projectsToday],
                'words' => ['total' => $wordsGenerated, 'today' => $wordsGeneratedToday],
            ],
            'recentActivity' => $activityLogs,
            'invites' => $invites,
            'charts' => [
                'revenue' => [
                    'daily' => $revenueDaily,
                    'monthly' => $revenueMonthly,
                ],
                'registrations' => [
                    'daily' => $registrationsDaily,
                    'monthly' => $registrationsMonthly,
                ],
            ],
        ]);
    }

    private function buildDailyRevenueSeries(int $days): array
    {
        $start = now()->subDays($days - 1)->startOfDay();
        $end = now()->endOfDay();

        $rows = Payment::successful()
            ->whereNotNull('paid_at')
            ->whereBetween('paid_at', [$start, $end])
            ->selectRaw('DATE(paid_at) as day, SUM(amount) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        return $this->fillDailySeries($start, $days, $rows, fn ($v) => round(((int) $v) / 100, 2));
    }

    private function buildDailyRegistrationsSeries(int $days): array
    {
        $start = now()->subDays($days - 1)->startOfDay();
        $end = now()->endOfDay();

        $rows = User::query()
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        return $this->fillDailySeries($start, $days, $rows, fn ($v) => (int) $v);
    }

    private function fillDailySeries(Carbon $start, int $days, Collection $rows, callable $transform): array
    {
        $labels = [];
        $data = [];

        for ($i = 0; $i < $days; $i++) {
            $day = $start->copy()->addDays($i)->toDateString();
            $labels[] = $day;
            $data[] = $transform($rows[$day] ?? 0);
        }

        return ['labels' => $labels, 'data' => $data];
    }

    private function buildMonthlyRevenueSeries(int $months): array
    {
        $start = now()->startOfMonth()->subMonths($months - 1);
        $end = now()->endOfMonth();

        $rows = Payment::successful()
            ->whereNotNull('paid_at')
            ->whereBetween('paid_at', [$start, $end])
            ->selectRaw('DATE_FORMAT(paid_at, "%Y-%m") as ym, SUM(amount) as total')
            ->groupBy('ym')
            ->pluck('total', 'ym');

        return $this->fillMonthlySeries($start, $months, $rows, fn ($v) => round(((int) $v) / 100, 2));
    }

    private function buildMonthlyRegistrationsSeries(int $months): array
    {
        $start = now()->startOfMonth()->subMonths($months - 1);
        $end = now()->endOfMonth();

        $rows = User::query()
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('DATE_FORMAT(created_at, "%Y-%m") as ym, COUNT(*) as total')
            ->groupBy('ym')
            ->pluck('total', 'ym');

        return $this->fillMonthlySeries($start, $months, $rows, fn ($v) => (int) $v);
    }

    private function fillMonthlySeries(Carbon $start, int $months, Collection $rows, callable $transform): array
    {
        $labels = [];
        $data = [];

        for ($i = 0; $i < $months; $i++) {
            $ym = $start->copy()->addMonths($i)->format('Y-m');
            $labels[] = $ym;
            $data[] = $transform($rows[$ym] ?? 0);
        }

        return ['labels' => $labels, 'data' => $data];
    }
}

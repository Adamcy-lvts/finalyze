<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Project;
use App\Models\RegistrationInvite;
use App\Models\User;
use App\Models\WordTransaction;
use Carbon\Carbon;
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

        $recentActivity = [
            ...Payment::with('user')
                ->latest()
                ->take(5)
                ->get()
                ->map(fn ($p) => [
                    'type' => 'payment',
                    'message' => "Payment of ₦".number_format($p->amount / 100, 0)." by ".$p->user?->email,
                    'time' => $p->created_at->diffForHumans(),
                ])->toArray(),
            ...User::latest()->take(5)->get()->map(fn ($u) => [
                'type' => 'user',
                'message' => "New signup: {$u->email}",
                'time' => $u->created_at->diffForHumans(),
            ])->toArray(),
        ];

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

        return Inertia::render('Admin/Dashboard', [
            'inviteOnlyEnabled' => (bool) config('registration.invite_only', true),
            'stats' => [
                'users' => ['total' => $totalUsers, 'today' => $newUsersToday],
                'revenue' => ['total' => $totalRevenueKobo / 100, 'today' => $todayRevenueKobo / 100],
                'projects' => ['total' => $totalProjects, 'today' => $projectsToday],
                'words' => ['total' => $wordsGenerated, 'today' => $wordsGeneratedToday],
            ],
            'recentActivity' => $recentActivity,
            'invites' => $invites,
        ]);
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Events\WordBalanceUpdated;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use App\Models\WordTransaction;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminUserController extends Controller
{
    public function index()
    {
        $users = User::query()
            ->withCount(['projects', 'payments'])
            ->orderByDesc('created_at')
            ->paginate(20)
            ->through(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_banned' => $user->is_banned,
                'last_active_at' => $user->last_active_at,
                'last_login_at' => $user->last_login_at,
                'is_online' => $user->isOnline(),
                'created_at' => $user->created_at,
                'projects_count' => $user->projects_count,
                'payments_count' => $user->payments_count,
                'roles' => $user->getRoleNames(),
                'word_balance' => $user->word_balance,
                'total_words_purchased' => $user->total_words_purchased,
                'package' => $user->successfulPayments()->latest()->with('wordPackage')->first()?->wordPackage?->name ?? 'None',
            ]);

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
        ]);
    }

    public function show(User $user)
    {
        $user->loadCount(['projects', 'payments']);

        $latestTransactions = WordTransaction::where('user_id', $user->id)
            ->latest()
            ->take(10)
            ->get()
            ->map(fn ($tx) => [
                'id' => $tx->id,
                'type' => $tx->type,
                'words' => $tx->words,
                'description' => $tx->description,
                'created_at' => $tx->created_at,
            ]);

        $recentActivities = ActivityLog::query()
            ->where('causer_id', $user->id)
            ->latest()
            ->take(10)
            ->get()
            ->map(fn ($activity) => [
                'id' => $activity->id,
                'type' => $activity->type,
                'message' => $activity->message,
                'created_at' => $activity->created_at,
            ]);

        return Inertia::render('Admin/Users/Show', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_banned' => $user->is_banned,
                'word_balance' => $user->word_balance,
                'projects_count' => $user->projects_count,
                'payments_count' => $user->payments_count,
                'last_active_at' => $user->last_active_at,
                'last_login_at' => $user->last_login_at,
                'is_online' => $user->isOnline(),
                'created_at' => $user->created_at,
                'roles' => $user->getRoleNames(),
            ],
            'transactions' => $latestTransactions,
            'activities' => $recentActivities,
        ]);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,'.$user->id,
        ]);

        $user->update($data);

        return back()->with('success', 'User updated');
    }

    public function resetPassword(Request $request, User $user)
    {
        $data = $request->validate([
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user->update([
            'password' => \Illuminate\Support\Facades\Hash::make($data['password']),
        ]);

        return back()->with('success', 'Password reset successfully');
    }

    public function ban(Request $request, User $user)
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $user->update([
            'is_banned' => true,
            'banned_at' => now(),
            'ban_reason' => $request->input('reason'),
            'banned_by' => $request->user()->id,
        ]);

        return back()->with('success', 'User banned');
    }

    public function unban(Request $request, User $user)
    {
        $user->update([
            'is_banned' => false,
            'banned_at' => null,
            'ban_reason' => null,
            'banned_by' => null,
        ]);

        return back()->with('success', 'User unbanned');
    }

    public function adjustBalance(Request $request, User $user)
    {
        $data = $request->validate([
            'words' => 'required|integer',
            'reason' => 'required|string|max:255',
        ]);

        $delta = (int) $data['words'];

        $user->word_balance = ($user->word_balance ?? 0) + $delta;
        $user->save();

        WordTransaction::create([
            'user_id' => $user->id,
            'type' => WordTransaction::TYPE_ADJUSTMENT,
            'words' => $delta,
            'balance_after' => $user->word_balance,
            'description' => $data['reason'],
            'reference_type' => WordTransaction::REF_ADMIN,
            'reference_id' => $request->user()->id,
        ]);

        // Broadcast real-time balance update to user's dashboard
        event(new WordBalanceUpdated($user, 'admin_adjustment'));

        return back()->with('success', 'Balance adjusted');
    }

    public function destroy(User $user)
    {
        $user->delete();

        return back()->with('success', 'User soft deleted');
    }

    public function forceDestroy(User $user)
    {
        $user->forceDelete();

        return back()->with('success', 'User permanently deleted');
    }

    public function impersonate(User $user)
    {
        auth()->user()->impersonate($user);

        return redirect('/dashboard');
    }

    public function stopImpersonation()
    {
        if (auth()->user()->isImpersonated()) {
            auth()->user()->leaveImpersonation();
        }

        return redirect()->route('admin.users.index');
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminUserController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Users/Index');
    }

    public function show(User $user)
    {
        return Inertia::render('Admin/Users/Show', ['userId' => $user->id]);
    }

    public function update(Request $request, User $user)
    {
        // Placeholder implementation
        return response()->json(['status' => 'ok']);
    }

    public function ban(Request $request, User $user)
    {
        return response()->json(['status' => 'ok']);
    }

    public function unban(Request $request, User $user)
    {
        return response()->json(['status' => 'ok']);
    }

    public function adjustBalance(Request $request, User $user)
    {
        return response()->json(['status' => 'ok']);
    }

    public function destroy(User $user)
    {
        return response()->json(['status' => 'ok']);
    }

    public function forceDestroy(User $user)
    {
        return response()->json(['status' => 'ok']);
    }

    public function impersonate(User $user)
    {
        return response()->json(['status' => 'ok']);
    }

    public function stopImpersonation()
    {
        return response()->json(['status' => 'ok']);
    }
}

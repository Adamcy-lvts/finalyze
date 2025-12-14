<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RegistrationInvite;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdminRegistrationInviteController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'count' => ['sometimes', 'integer', 'min:1', 'max:25'],
            'max_uses' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'expires_in_days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        $count = $data['count'] ?? 1;
        $expiresAt = isset($data['expires_in_days'])
            ? now()->addDays($data['expires_in_days'])
            : null;

        for ($i = 0; $i < $count; $i++) {
            RegistrationInvite::createUnique([
                'max_uses' => $data['max_uses'] ?? 1,
                'expires_at' => $expiresAt,
                'created_by' => $request->user()?->id,
            ]);
        }

        return back()->with('success', $count === 1 ? 'Invite created.' : "{$count} invites created.");
    }

    public function revoke(Request $request, RegistrationInvite $invite): RedirectResponse
    {
        if ($invite->revoked_at === null) {
            $invite->update(['revoked_at' => now()]);
        }

        return back()->with('success', 'Invite revoked.');
    }
}


<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
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

        $created = [];
        for ($i = 0; $i < $count; $i++) {
            $invite = RegistrationInvite::createUnique([
                'max_uses' => $data['max_uses'] ?? 1,
                'expires_at' => $expiresAt,
                'created_by' => $request->user()?->id,
            ]);
            $created[] = $invite;
        }

        if (! empty($created)) {
            $codes = array_map(fn ($inv) => $inv->code, $created);
            ActivityLog::record(
                'registration_invite.created',
                $count === 1 ? "Registration invite created: {$codes[0]}" : "{$count} registration invites created",
                $created[0],
                $request->user(),
                [
                    'count' => $count,
                    'codes' => $codes,
                    'max_uses' => $data['max_uses'] ?? 1,
                    'expires_at' => $expiresAt?->toDateTimeString(),
                ]
            );
        }

        return back()->with('success', $count === 1 ? 'Invite created.' : "{$count} invites created.");
    }

    public function revoke(Request $request, RegistrationInvite $invite): RedirectResponse
    {
        if ($invite->revoked_at === null) {
            $invite->update(['revoked_at' => now()]);
        }

        ActivityLog::record(
            'registration_invite.revoked',
            "Registration invite revoked: {$invite->code}",
            $invite,
            $request->user()
        );

        return back()->with('success', 'Invite revoked.');
    }
}

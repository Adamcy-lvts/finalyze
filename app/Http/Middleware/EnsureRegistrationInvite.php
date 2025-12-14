<?php

namespace App\Http\Middleware;

use App\Models\RegistrationInvite;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRegistrationInvite
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('registration.invite_only', true)) {
            return $next($request);
        }

        $inviteId = $request->session()->get('registration_invite_id');
        if (! $inviteId) {
            return redirect()->route('invite.form');
        }

        $invite = RegistrationInvite::query()->whereKey($inviteId)->valid()->first();
        if (! $invite) {
            $request->session()->forget('registration_invite_id');
            return redirect()
                ->route('invite.form')
                ->with('error', 'Invite code is invalid or expired. Please enter a valid invite code.');
        }

        return $next($request);
    }
}


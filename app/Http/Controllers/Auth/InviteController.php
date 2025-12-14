<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\RegistrationInvite;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class InviteController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('auth/InviteOnly', [
            'prefillCode' => null,
            'inviteOnlyEnabled' => (bool) config('registration.invite_only', true),
        ]);
    }

    public function show(string $code): Response
    {
        return Inertia::render('auth/InviteOnly', [
            'prefillCode' => $code,
            'inviteOnlyEnabled' => (bool) config('registration.invite_only', true),
        ]);
    }

    public function verify(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:64'],
        ]);

        $code = strtoupper(trim($data['code']));

        $invite = RegistrationInvite::query()
            ->where('code', $code)
            ->valid()
            ->first();

        if (! $invite) {
            throw ValidationException::withMessages([
                'code' => 'Invalid or expired invite code.',
            ]);
        }

        $request->session()->put('registration_invite_id', $invite->id);

        return redirect()->route('register');
    }

    public function clear(Request $request): RedirectResponse
    {
        $request->session()->forget('registration_invite_id');

        return redirect()->route('invite.form');
    }
}


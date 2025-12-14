<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\RegistrationInvite;
use App\Models\RegistrationInviteRedemption;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Show the registration page.
     */
    public function create(): Response
    {
        return Inertia::render('auth/Register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $inviteId = null;
        if (config('registration.invite_only', true)) {
            $inviteId = $request->session()->get('registration_invite_id');
            if (! $inviteId) {
                return redirect()->route('invite.form');
            }
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = null;

        try {
            DB::transaction(function () use ($request, $inviteId, &$user) {
                $invite = null;

                if ($inviteId) {
                    $invite = RegistrationInvite::query()
                        ->whereKey($inviteId)
                        ->lockForUpdate()
                        ->first();

                    if (! $invite || ! $invite->isValid()) {
                        throw new \RuntimeException('registration_invite_invalid');
                    }
                }

                $user = User::create([
                    'name' => $request->name,
                    'email' => $request->email,
                    'password' => Hash::make($request->password),
                ]);

                if ($invite) {
                    $invite->increment('uses');

                    RegistrationInviteRedemption::create([
                        'invite_id' => $invite->id,
                        'user_id' => $user->id,
                        'email' => $user->email,
                        'ip' => $request->ip(),
                        'user_agent' => (string) $request->userAgent(),
                        'redeemed_at' => now(),
                    ]);
                }
            });
        } catch (\RuntimeException $e) {
            if ($e->getMessage() !== 'registration_invite_invalid') {
                throw $e;
            }

            $request->session()->forget('registration_invite_id');

            return redirect()
                ->route('invite.form')
                ->with('error', 'Invite code is invalid or expired. Please enter a valid invite code.');
        }

        if ($inviteId) {
            $request->session()->forget('registration_invite_id');
        }

        event(new Registered($user));

        Auth::login($user);

        return to_route('dashboard');
    }
}

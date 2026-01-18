<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AffiliateInvite;
use App\Models\User;
use App\Services\AffiliateService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class AffiliateAuthController extends Controller
{
    public function __construct(
        private AffiliateService $affiliateService
    ) {}

    public function showRegistration(Request $request): Response
    {
        $inviteId = $request->session()->get('affiliate_invite_id');

        return Inertia::render('Affiliate/Register', [
            'registrationOpen' => $this->affiliateService->isRegistrationOpen(),
            'affiliateEnabled' => $this->affiliateService->isEnabled(),
            'hasInvite' => (bool) $inviteId,
        ]);
    }

    public function validateInvite(string $code, Request $request): RedirectResponse
    {
        $invite = $this->affiliateService->validateInvite($code);

        if (! $invite) {
            return redirect()->route('affiliate.register')->with('error', 'Invite code is invalid or expired.');
        }

        $request->session()->put('affiliate_invite_id', $invite->id);

        return redirect()->route('affiliate.register');
    }

    public function register(Request $request): RedirectResponse
    {
        if (! $this->affiliateService->isEnabled()) {
            return redirect()->route('affiliate.register')->with('error', 'Affiliate program is currently disabled.');
        }

        $inviteId = $request->session()->get('affiliate_invite_id');
        $registrationOpen = $this->affiliateService->isRegistrationOpen();

        if (! $registrationOpen && ! $inviteId) {
            return redirect()->route('affiliate.register')->with('error', 'Affiliate registration is currently closed.');
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
                    $invite = AffiliateInvite::query()->whereKey($inviteId)->first();

                    if (! $invite || ! $invite->isValid()) {
                        throw new \RuntimeException('affiliate_invite_invalid');
                    }
                }

                $user = User::create([
                    'name' => $request->name,
                    'email' => $request->email,
                    'password' => Hash::make($request->password),
                    'affiliate_status' => 'approved',
                    'affiliate_requested_at' => now(),
                    'affiliate_approved_at' => now(),
                    'affiliate_is_pure' => true,
                ]);

                $user->assignRole('affiliate');

                if ($invite) {
                    $this->affiliateService->redeemInvite($invite, $user, $request->ip(), (string) $request->userAgent());
                }
            });
        } catch (\RuntimeException $e) {
            if ($e->getMessage() !== 'affiliate_invite_invalid') {
                throw $e;
            }

            $request->session()->forget('affiliate_invite_id');

            return redirect()->route('affiliate.register')->with('error', 'Invite code is invalid or expired.');
        }

        $request->session()->forget('affiliate_invite_id');

        event(new Registered($user));
        Auth::login($user);

        return redirect()->route('affiliate.dashboard');
    }
}

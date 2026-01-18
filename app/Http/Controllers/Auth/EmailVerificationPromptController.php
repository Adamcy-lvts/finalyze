<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmailVerificationPromptController extends Controller
{
    /**
     * Show the email verification prompt page.
     */
    public function __invoke(Request $request): RedirectResponse|Response
    {
        if (! $this->isVerificationRequired()) {
            return redirect()->intended(route('dashboard', absolute: false));
        }

        return $request->user()->hasVerifiedEmail()
                    ? redirect()->intended(route('dashboard', absolute: false))
                    : Inertia::render('auth/VerifyEmail', ['status' => $request->session()->get('status')]);
    }

    private function isVerificationRequired(): bool
    {
        $setting = SystemSetting::query()->where('key', 'auth.require_email_verification')->first();
        if (! $setting) {
            return true;
        }

        $value = $setting->value;
        if (is_array($value)) {
            $value = $value['value'] ?? $value;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
    }
}

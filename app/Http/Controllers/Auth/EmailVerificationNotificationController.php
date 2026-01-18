<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmailVerificationNotificationController extends Controller
{
    /**
     * Send a new email verification notification.
     */
    public function store(Request $request): RedirectResponse
    {
        if (! $this->isVerificationRequired()) {
            return redirect()->intended(route('dashboard', absolute: false));
        }

        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended(route('dashboard', absolute: false));
        }

        $request->user()->sendEmailVerificationNotification();

        return back()->with('status', 'verification-link-sent');
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

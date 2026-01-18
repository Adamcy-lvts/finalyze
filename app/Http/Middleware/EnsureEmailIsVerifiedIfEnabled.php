<?php

namespace App\Http\Middleware;

use App\Models\SystemSetting;
use Closure;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmailIsVerifiedIfEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->isVerificationRequired()) {
            return $next($request);
        }

        $user = $request->user();
        if (! $user || ! ($user instanceof MustVerifyEmail)) {
            return $next($request);
        }

        if ($user->hasVerifiedEmail()) {
            return $next($request);
        }

        return $request->expectsJson()
            ? response()->json(['message' => 'Your email address is not verified.'], 403)
            : redirect()->route('verification.notice');
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

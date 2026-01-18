<?php

namespace App\Listeners;

use App\Models\SystemSetting;
use Illuminate\Auth\Events\Registered;

class SendEmailVerificationIfEnabled
{
    public function handle(Registered $event): void
    {
        if (! $this->isVerificationRequired()) {
            return;
        }

        $event->user->sendEmailVerificationNotification();
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

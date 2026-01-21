<?php

namespace App\Services;

use App\Models\AffiliateInvite;
use App\Models\AffiliateInviteRedemption;
use App\Models\SystemSetting;
use App\Models\User;
use App\Notifications\AffiliateRequestApproved;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AffiliateService
{
    public function isEnabled(): bool
    {
        return (bool) ($this->getSettingValue('affiliate.enabled', 'enabled') ?? false);
    }

    public function isRegistrationOpen(): bool
    {
        return (bool) ($this->getSettingValue('affiliate.registration_open', 'enabled') ?? false);
    }

    public function getDefaultCommissionRate(): float
    {
        return (float) ($this->getSettingValue('affiliate.commission_percentage', 'percentage') ?? 10);
    }

    public function getMinimumPaymentAmount(): int
    {
        return (int) ($this->getSettingValue('affiliate.minimum_payment_amount', 'amount') ?? 100000);
    }

    public function getFeeBearer(): string
    {
        return (string) ($this->getSettingValue('affiliate.fee_bearer', 'bearer') ?? 'account');
    }

    public function isPromoPopupEnabled(): bool
    {
        return (bool) ($this->getSettingValue('affiliate.promo_popup_enabled', 'enabled') ?? false);
    }

    public function getPromoPopupDelayDays(): int
    {
        return (int) ($this->getSettingValue('affiliate.promo_popup_delay_days', 'days') ?? 7);
    }

    public function createInvite(User $admin, string $type, ?int $maxUses, ?Carbon $expiresAt, ?string $note): AffiliateInvite
    {
        do {
            $code = strtoupper(Str::random(10));
        } while (AffiliateInvite::where('code', $code)->exists());

        return AffiliateInvite::create([
            'code' => $code,
            'created_by' => $admin->id,
            'type' => $type,
            'max_uses' => $type === AffiliateInvite::TYPE_SINGLE_USE ? 1 : $maxUses,
            'uses' => 0,
            'expires_at' => $expiresAt,
            'is_active' => true,
            'note' => $note,
        ]);
    }

    public function validateInvite(string $code): ?AffiliateInvite
    {
        $invite = AffiliateInvite::where('code', strtoupper(trim($code)))->first();

        if (! $invite || ! $invite->isValid()) {
            return null;
        }

        return $invite;
    }

    public function redeemInvite(AffiliateInvite $invite, User $user, ?string $ip, ?string $userAgent): void
    {
        DB::transaction(function () use ($invite, $user, $ip, $userAgent) {
            $lockedInvite = AffiliateInvite::whereKey($invite->id)->lockForUpdate()->first();

            if (! $lockedInvite || ! $lockedInvite->isValid()) {
                throw new \RuntimeException('affiliate_invite_invalid');
            }

            $lockedInvite->markUsed();

            AffiliateInviteRedemption::create([
                'invite_id' => $lockedInvite->id,
                'user_id' => $user->id,
                'ip' => $ip,
                'user_agent' => $userAgent,
                'redeemed_at' => now(),
            ]);
        });
    }

    public function requestAffiliateAccess(User $user): bool
    {
        if (! $user->canRequestAffiliateAccess()) {
            return false;
        }

        $user->update([
            'affiliate_status' => 'pending',
            'affiliate_requested_at' => now(),
            'affiliate_notes' => null,
            'affiliate_approved_at' => null,
        ]);

        Log::info('Affiliate access requested', ['user_id' => $user->id]);

        return true;
    }

    public function approveAffiliateRequest(User $user, User $admin): void
    {
        $user->update([
            'affiliate_status' => 'approved',
            'affiliate_approved_at' => now(),
            'affiliate_is_pure' => false,
        ]);

        $user->assignRole('affiliate');
        $user->notify(new AffiliateRequestApproved());

        Log::info('Affiliate request approved', [
            'user_id' => $user->id,
            'admin_id' => $admin->id,
        ]);
    }

    public function rejectAffiliateRequest(User $user, User $admin, ?string $reason): void
    {
        $user->update([
            'affiliate_status' => 'rejected',
            'affiliate_notes' => $reason,
            'affiliate_approved_at' => null,
        ]);

        Log::info('Affiliate request rejected', [
            'user_id' => $user->id,
            'admin_id' => $admin->id,
            'reason' => $reason,
        ]);
    }

    public function shouldShowPromoPopup(User $user): bool
    {
        if (! $this->isEnabled() || ! $this->isPromoPopupEnabled()) {
            return false;
        }

        if ($user->isAffiliate() || $user->hasPendingAffiliateRequest()) {
            return false;
        }

        if ($user->affiliate_promo_dismissed_at) {
            return false;
        }

        $delayDays = $this->getPromoPopupDelayDays();
        $eligibleDate = $user->created_at?->copy()->addDays($delayDays);

        return $eligibleDate ? $eligibleDate->isPast() : false;
    }

    public function dismissPromoPopup(User $user): void
    {
        $user->update([
            'affiliate_promo_dismissed_at' => now(),
        ]);
    }

    public function updateSettings(array $data): void
    {
        DB::transaction(function () use ($data) {
            if (isset($data['enabled'])) {
                $this->updateSetting('affiliate.enabled', ['enabled' => (bool) $data['enabled']], 'boolean');
            }

            if (isset($data['registration_open'])) {
                $this->updateSetting('affiliate.registration_open', ['enabled' => (bool) $data['registration_open']], 'boolean');
            }

            if (isset($data['commission_percentage'])) {
                $this->updateSetting('affiliate.commission_percentage', ['percentage' => (float) $data['commission_percentage']], 'integer');
            }

            if (isset($data['minimum_payment_amount'])) {
                $this->updateSetting('affiliate.minimum_payment_amount', ['amount' => (int) $data['minimum_payment_amount']], 'integer');
            }

            if (isset($data['fee_bearer'])) {
                $this->updateSetting('affiliate.fee_bearer', ['bearer' => $data['fee_bearer']], 'string');
            }

            if (isset($data['promo_popup_enabled'])) {
                $this->updateSetting('affiliate.promo_popup_enabled', ['enabled' => (bool) $data['promo_popup_enabled']], 'boolean');
            }

            if (isset($data['promo_popup_delay_days'])) {
                $this->updateSetting('affiliate.promo_popup_delay_days', ['days' => (int) $data['promo_popup_delay_days']], 'integer');
            }
        });

        Log::info('Affiliate settings updated', $data);
    }

    public function getSettings(): array
    {
        return [
            'enabled' => $this->isEnabled(),
            'registration_open' => $this->isRegistrationOpen(),
            'commission_percentage' => $this->getDefaultCommissionRate(),
            'minimum_payment_amount' => $this->getMinimumPaymentAmount(),
            'fee_bearer' => $this->getFeeBearer(),
            'promo_popup_enabled' => $this->isPromoPopupEnabled(),
            'promo_popup_delay_days' => $this->getPromoPopupDelayDays(),
        ];
    }

    private function updateSetting(string $key, array $value, string $type): void
    {
        SystemSetting::updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'type' => $type, 'group' => 'affiliate']
        );
    }

    private function getSettingValue(string $key, string $valueKey): mixed
    {
        $setting = SystemSetting::where('key', $key)->first();

        return $setting?->value[$valueKey] ?? null;
    }
}

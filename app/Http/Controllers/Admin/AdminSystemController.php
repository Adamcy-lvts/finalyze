<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FeatureFlag;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Inertia\Inertia;

class AdminSystemController extends Controller
{
    public function features()
    {
        $flags = FeatureFlag::orderBy('key')->get([
            'id',
            'key',
            'name',
            'description',
            'is_enabled',
            'metadata',
            'created_at',
            'updated_at',
        ]);

        return Inertia::render('Admin/System/Features', [
            'flags' => $flags,
        ]);
    }

    public function updateFeature(Request $request, FeatureFlag $flag)
    {
        $data = $request->validate([
            'is_enabled' => 'required|boolean',
        ]);

        $flag->update(['is_enabled' => $data['is_enabled']]);

        return back()->with('success', 'Feature flag updated');
    }

    public function settings()
    {
        $settings = SystemSetting::orderBy('group')->orderBy('key')->get([
            'id',
            'key',
            'value',
            'type',
            'group',
            'description',
            'created_at',
            'updated_at',
        ]);

        return Inertia::render('Admin/System/Settings', [
            'settings' => $settings,
        ]);
    }

    public function updateSettings(Request $request)
    {
        \Illuminate\Support\Facades\Log::info('Settings update request:', $request->all());

        $data = $request->validate([
            'settings' => 'required|array',
            'settings.*.key' => 'required|string|exists:system_settings,key',
            'settings.*.value' => 'nullable',
        ]);

        $structuredKeys = [
            'affiliate.enabled' => ['enabled', 'bool'],
            'affiliate.registration_open' => ['enabled', 'bool'],
            'affiliate.commission_percentage' => ['percentage', 'float'],
            'affiliate.minimum_payment_amount' => ['amount', 'int'],
            'affiliate.fee_bearer' => ['bearer', 'string'],
            'affiliate.promo_popup_enabled' => ['enabled', 'bool'],
            'affiliate.promo_popup_delay_days' => ['days', 'int'],
            'referral.enabled' => ['enabled', 'bool'],
            'referral.commission_percentage' => ['percentage', 'float'],
            'referral.minimum_payment_amount' => ['amount', 'int'],
            'referral.fee_bearer' => ['bearer', 'string'],
        ];

        foreach ($data['settings'] as $item) {
            $setting = SystemSetting::where('key', $item['key'])->first();
            if ($setting) {
                // Determine if we need to decode JSON strings
                $newValue = $item['value'];
                
                // If the model expects an array (JSON) but we received a string that looks like JSON, try to decode it
                // This prevents double-encoding
                if ($setting->hasCast('value', 'array') && is_string($newValue)) {
                    $decoded = json_decode($newValue, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                       $newValue = $decoded;
                    }
                }
                
                if (isset($structuredKeys[$item['key']])) {
                    [$valueKey, $castType] = $structuredKeys[$item['key']];
                    if (! is_array($newValue) || ! array_key_exists($valueKey, $newValue)) {
                        $scalar = $newValue;
                        $newValue = match ($castType) {
                            'bool' => filter_var($scalar, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
                            'int' => (int) $scalar,
                            'float' => (float) $scalar,
                            default => (string) $scalar,
                        };
                        $newValue = [$valueKey => $newValue];
                    }
                }

                \Illuminate\Support\Facades\Log::info("Updating setting {$item['key']}", ['old' => $setting->value, 'new' => $newValue]);
                $setting->update(['value' => $newValue]);
            }
        }

        return back()->with('success', 'Settings updated');
    }

    public function clearCache()
    {
        Artisan::call('cache:clear');
        Artisan::call('config:clear');

        return back()->with('success', 'Cache cleared');
    }
}

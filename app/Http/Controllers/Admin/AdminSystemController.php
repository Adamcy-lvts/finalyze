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
        $data = $request->validate([
            'settings' => 'required|array',
            'settings.*.key' => 'required|string|exists:system_settings,key',
            'settings.*.value' => 'required',
        ]);

        foreach ($data['settings'] as $item) {
            $setting = SystemSetting::where('key', $item['key'])->first();
            if ($setting) {
                $setting->update(['value' => $item['value']]);
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

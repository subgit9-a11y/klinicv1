<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\TenantSetting;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function index()
    {
        $tenantId = app(TenantContext::class)->id();

        $settings = TenantSetting::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->orderBy('category')
            ->orderBy('key')
            ->get();

        return view('settings.index', ['settings' => $settings]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'settings' => ['required', 'array'],
            'settings.*.key' => ['required', 'string', 'max:100'],
            'settings.*.value' => ['nullable', 'string', 'max:500'],
            'settings.*.category' => ['nullable', 'string', 'max:50'],
        ]);

        $tenantId = app(TenantContext::class)->id();

        foreach ($validated['settings'] as $row) {
            TenantSetting::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('key', $row['key'])
                ->update(['value' => $row['value'] ?? null]);
        }

        return redirect()->route('settings.index')->with('status', 'Settings saved.');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'key' => ['required', 'string', 'max:100'],
            'value' => ['nullable', 'string', 'max:500'],
            'category' => ['nullable', 'string', 'max:50'],
        ]);

        $tenantId = app(TenantContext::class)->id();

        TenantSetting::withoutGlobalScopes()->updateOrCreate(
            ['tenant_id' => $tenantId, 'key' => $validated['key']],
            ['value' => $validated['value'] ?? null, 'category' => $validated['category'] ?? 'general'],
        );

        return redirect()->route('settings.index')->with('status', "Setting '{$validated['key']}' added.");
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use App\Models\IntegrationAccount;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Manages per-provider integration accounts (integration_accounts table).
 *
 * Secrets are stored as individually Crypt-encrypted values inside the
 * `credentials_encrypted` JSON column and are NEVER returned decrypted by the
 * UI — only masked (last-4) previews. Active GLOBAL (tenant_id = null)
 * accounts override the env-based `services.*` config at app boot via
 * applyToConfig(); tenant-scoped accounts are resolved explicitly through
 * credentialsFor() by code that knows the tenant.
 */
class IntegrationAccountService
{
    /**
     * provider => [credential key => config target key].
     *
     * @var array<string, array<string, string>>
     */
    public const PROVIDERS = [
        'cashfree' => [
            'app_id' => 'services.cashfree.app_id',
            'secret_key' => 'services.cashfree.secret_key',
            'base_url' => 'services.cashfree.base_url',
        ],
        'gemini' => [
            'api_key' => 'services.gemini.api_key',
            'base_url' => 'services.gemini.base_url',
            'model' => 'services.gemini.model',
        ],
        'whatsapp' => [
            'api_token' => 'services.whatsapp.api_token',
            'phone_number_id' => 'services.whatsapp.phone_number_id',
            'api_version' => 'services.whatsapp.api_version',
        ],
        'msg91' => [
            'auth_key' => 'services.msg91.auth_key',
            'sender_id' => 'services.msg91.sender_id',
            'route' => 'services.msg91.route',
        ],
        'resend' => [
            'api_key' => 'services.resend.key',
        ],
        'google_meet' => [
            'client_id' => 'services.google.meet_client_id',
            'client_secret' => 'services.google.meet_client_secret',
            'refresh_token' => 'services.google.meet_refresh_token',
        ],
        'google_vision' => [
            'api_key' => 'services.google_vision.api_key',
            'base_url' => 'services.google_vision.base_url',
        ],
    ];

    /**
     * @param  array<string, string>  $credentials  plaintext values; empty strings are skipped
     */
    public function upsert(string $provider, array $credentials, ?int $tenantId = null, ?string $name = null): IntegrationAccount
    {
        Validator::make(
            ['provider' => $provider, 'tenant_id' => $tenantId],
            [
                'provider' => ['required', Rule::in(array_keys(self::PROVIDERS))],
                'tenant_id' => ['nullable', 'integer', 'exists:tenants,id'],
            ],
        )->validate();

        $knownKeys = array_keys(self::PROVIDERS[$provider]);
        $encrypted = [];

        foreach ($credentials as $key => $value) {
            if (! in_array($key, $knownKeys, true)) {
                throw ValidationException::withMessages(['credentials' => "Unknown credential key '{$key}' for provider '{$provider}'."]);
            }
            if ($value !== null && $value !== '') {
                $encrypted[$key] = Crypt::encryptString((string) $value);
            }
        }

        if ($encrypted === []) {
            throw ValidationException::withMessages(['credentials' => 'Provide at least one credential value.']);
        }

        $account = IntegrationAccount::withoutGlobalScopes()
            ->where('provider', $provider)
            ->where('tenant_id', $tenantId)
            ->first();

        $merged = $account?->credentials_encrypted ?? [];
        // Rotate only the keys supplied; keep previously-set keys not in this payload.
        $merged = array_merge($merged, $encrypted);

        return IntegrationAccount::withoutGlobalScopes()->updateOrCreate(
            ['tenant_id' => $tenantId, 'provider' => $provider],
            [
                'name' => $name ?? ($account?->name ?? ucfirst(str_replace('_', ' ', $provider)).' account'),
                'credentials_encrypted' => $merged,
                'is_active' => true,
            ],
        );
    }

    /**
     * Decrypted credentials — tenant-specific account wins over global.
     *
     * @return array<string, string>
     */
    public function credentialsFor(string $provider, ?int $tenantId = null): array
    {
        $accounts = IntegrationAccount::withoutGlobalScopes()
            ->where('provider', $provider)
            ->where('is_active', true)
            ->where(function ($query) use ($tenantId) {
                $query->where('tenant_id', $tenantId)->orWhereNull('tenant_id');
            })
            ->orderByRaw('tenant_id IS NULL') // tenant-specific first
            ->get();

        $creds = [];
        foreach ($accounts as $account) {
            foreach ((array) $account->credentials_encrypted as $key => $encrypted) {
                if (isset($creds[$key])) {
                    continue;
                }
                try {
                    $creds[$key] = Crypt::decryptString((string) $encrypted);
                } catch (\Throwable) {
                    // Un-decryptable entry (e.g. rotated APP_KEY) — treat as unset.
                }
            }
        }

        return $creds;
    }

    /**
     * Masked preview for UI display — keys visible, values redacted to last-4.
     *
     * @return array<string, string>
     */
    public function masked(IntegrationAccount $account): array
    {
        $preview = [];
        foreach ((array) $account->credentials_encrypted as $key => $encrypted) {
            try {
                $plain = Crypt::decryptString((string) $encrypted);
                $preview[$key] = '••••'.substr($plain, -4);
            } catch (\Throwable) {
                $preview[$key] = '••••';
            }
        }

        return $preview;
    }

    public function toggle(IntegrationAccount $account): IntegrationAccount
    {
        $account->update(['is_active' => ! $account->is_active]);

        return $account->refresh();
    }

    public function delete(IntegrationAccount $account): void
    {
        $account->delete();
    }

    /**
     * Apply ACTIVE GLOBAL accounts over the env-based services config.
     * Providers read services.* at construction, so a boot-time override is
     * enough for the rest of the request/worker lifecycle.
     */
    public function applyToConfig(): void
    {
        $accounts = IntegrationAccount::withoutGlobalScopes()
            ->whereNull('tenant_id')
            ->where('is_active', true)
            ->get();

        foreach ($accounts as $account) {
            $map = self::PROVIDERS[$account->provider] ?? [];
            foreach ((array) $account->credentials_encrypted as $key => $encrypted) {
                $configKey = $map[$key] ?? null;
                if ($configKey === null) {
                    continue;
                }
                try {
                    $value = Crypt::decryptString((string) $encrypted);
                } catch (\Throwable) {
                    continue;
                }
                if ($value !== '') {
                    config([$configKey => $value]);
                }
            }
        }
    }
}

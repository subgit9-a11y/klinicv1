<?php

declare(strict_types=1);

namespace Tests\Feature\Integrations;

use App\Models\IntegrationAccount;
use App\Models\Tenant;
use App\Services\Integrations\IntegrationAccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class IntegrationAccountServiceTest extends TestCase
{
    use RefreshDatabase;

    private IntegrationAccountService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new IntegrationAccountService;
    }

    public function test_upsert_stores_encrypted_credentials(): void
    {
        $account = $this->service->upsert('cashfree', ['app_id' => 'cf_app_123', 'secret_key' => 'secret123456']);

        $this->assertDatabaseHas('integration_accounts', ['provider' => 'cashfree', 'tenant_id' => null]);
        $raw = $account->refresh()->credentials_encrypted;
        $this->assertStringNotContainsString('cf_app_123', (string) json_encode($raw));
        $this->assertSame('cf_app_123', Crypt::decryptString($raw['app_id']));
        $this->assertSame('secret123456', Crypt::decryptString($raw['secret_key']));
    }

    public function test_credentials_for_returns_decrypted_values(): void
    {
        $this->service->upsert('msg91', ['auth_key' => 'msg-key-xyz']);

        $this->assertSame(['auth_key' => 'msg-key-xyz'], $this->service->credentialsFor('msg91'));
    }

    public function test_tenant_specific_account_wins_over_global(): void
    {
        $tenant = Tenant::factory()->create();
        $this->service->upsert('cashfree', ['app_id' => 'global-app']);
        $this->service->upsert('cashfree', ['app_id' => 'tenant-app'], $tenant->id);

        $this->assertSame('tenant-app', $this->service->credentialsFor('cashfree', $tenant->id)['app_id']);
        $this->assertSame('global-app', $this->service->credentialsFor('cashfree')['app_id']);
    }

    public function test_upsert_rotates_supplied_keys_only(): void
    {
        $this->service->upsert('cashfree', ['app_id' => 'old-app', 'secret_key' => 'old-secret']);
        $this->service->upsert('cashfree', ['secret_key' => 'new-secret']);

        $creds = $this->service->credentialsFor('cashfree');
        $this->assertSame('old-app', $creds['app_id']);
        $this->assertSame('new-secret', $creds['secret_key']);
        $this->assertSame(1, IntegrationAccount::withoutGlobalScopes()->where('provider', 'cashfree')->count());
    }

    public function test_unknown_credential_key_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        $this->service->upsert('cashfree', ['password_field' => 'x']);
    }

    public function test_unknown_provider_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        $this->service->upsert('evil-script', ['api_key' => 'x']);
    }

    public function test_masked_shows_only_last_four(): void
    {
        $account = $this->service->upsert('whatsapp', ['api_token' => 'EAA123456789']);

        $masked = $this->service->masked($account->refresh());

        $this->assertSame(['api_token' => '••••6789'], $masked);
        $this->assertStringNotContainsString('EAA123456789', (string) json_encode($masked));
    }

    public function test_apply_to_config_overrides_env_for_active_global_accounts(): void
    {
        $this->service->upsert('gemini', ['api_key' => 'db-gemini-key', 'model' => 'gemini-pro']);
        config(['services.gemini.api_key' => 'env-key']);

        $this->service->applyToConfig();

        $this->assertSame('db-gemini-key', config('services.gemini.api_key'));
        $this->assertSame('gemini-pro', config('services.gemini.model'));
    }

    public function test_apply_to_config_skips_tenant_scoped_and_inactive_accounts(): void
    {
        $tenant = Tenant::factory()->create();
        $this->service->upsert('gemini', ['api_key' => 'tenant-key'], $tenant->id);
        $this->service->toggle($this->service->upsert('resend', ['api_key' => 'disabled-key']));

        $this->service->applyToConfig();

        $this->assertNotSame('tenant-key', (string) config('services.gemini.api_key'));
        $this->assertNotSame('disabled-key', (string) config('services.resend.key'));
    }

    public function test_toggle_disables_and_enables(): void
    {
        $account = $this->service->upsert('resend', ['api_key' => 'abc']);

        $this->service->toggle($account);
        $this->assertFalse($account->refresh()->is_active);

        $this->service->toggle($account);
        $this->assertTrue($account->refresh()->is_active);
    }

    public function test_model_does_not_serialize_credentials(): void
    {
        $account = $this->service->upsert('cashfree', ['app_id' => 'cf_app_123']);

        $json = $account->toJson();
        $this->assertStringNotContainsString('cf_app_123', $json);
        $this->assertArrayNotHasKey('credentials_encrypted', $account->toArray());
    }
}

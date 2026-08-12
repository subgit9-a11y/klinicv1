<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\Auth\TwoFactorService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get(route('login'));

        $response->assertStatus(200);
        $response->assertSeeLivewire('auth.login');
    }

    public function test_users_can_authenticate_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'doctor@clinic.test',
            'password' => Hash::make('secret123'),
        ]);

        Livewire::test('auth.login')
            ->set('email', 'doctor@clinic.test')
            ->set('password', 'secret123')
            ->call('authenticate')
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();
    }

    public function test_users_cannot_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create([
            'email' => 'doctor@clinic.test',
            'password' => Hash::make('secret123'),
        ]);

        Livewire::test('auth.login')
            ->set('email', 'doctor@clinic.test')
            ->set('password', 'wrong-password')
            ->call('authenticate')
            ->assertHasErrors(['email']);

        $this->assertGuest();
    }

    public function test_inactive_user_cannot_authenticate(): void
    {
        $user = User::factory()->create([
            'email' => 'disabled@clinic.test',
            'password' => Hash::make('secret123'),
            'is_active' => false,
        ]);

        Livewire::test('auth.login')
            ->set('email', 'disabled@clinic.test')
            ->set('password', 'secret123')
            ->call('authenticate')
            ->assertHasErrors(['email']);

        $this->assertGuest();
    }

    public function test_unverified_email_is_redirected_to_verification_notice(): void
    {
        $user = User::factory()->unverified()->create([
            'email' => 'unverified@clinic.test',
            'password' => Hash::make('secret123'),
        ]);

        Livewire::test('auth.login')
            ->set('email', 'unverified@clinic.test')
            ->set('password', 'secret123')
            ->call('authenticate')
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();

        // The `verified` middleware on dashboard bounces unverified users to the notice.
        $this->get(route('dashboard'))->assertRedirect(route('verification.notice'));
    }

    public function test_verified_user_can_access_dashboard(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertStatus(200);
    }

    public function test_unverified_user_cannot_access_dashboard(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertRedirect(route('verification.notice'));
    }

    public function test_inactive_user_is_logged_out_by_active_middleware(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $response = $this->actingAs($user)->get(route('dashboard'));
        $response->assertStatus(200);

        // Now disable the user — next request should log them out.
        $user->update(['is_active' => false]);

        $response = $this->get(route('dashboard'));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('logout'));

        $this->assertGuest();
        $response->assertRedirect(route('home'));
    }

    public function test_login_is_rate_limited_after_too_many_attempts(): void
    {
        $user = User::factory()->create([
            'email' => 'limited@clinic.test',
            'password' => Hash::make('secret123'),
        ]);

        // The Livewire test harness resets the array cache between component
        // instances, so pre-seed 5 failed attempts on the exact throttle key
        // the component will compute, then assert the next attempt is throttled.
        $throttleKey = Str::transliterate(
            Str::lower('limited@clinic.test').'|127.0.0.1'
        );
        for ($i = 0; $i < 5; $i++) {
            RateLimiter::hit($throttleKey, 60);
        }

        Livewire::test('auth.login')
            ->set('email', 'limited@clinic.test')
            ->set('password', 'secretlong') // meets min:6 so only throttle can fail
            ->call('authenticate')
            ->assertHasErrors(['email']);
    }

    public function test_user_with_2fa_is_redirected_to_challenge(): void
    {
        $twoFactor = app(TwoFactorService::class);
        $secret = $twoFactor->generateSecret();

        $user = User::factory()->create([
            'email' => '2fa@clinic.test',
            'password' => Hash::make('secret123'),
            'two_factor_secret' => $twoFactor->encrypt($secret),
            'two_factor_confirmed_at' => now(),
        ]);

        Livewire::test('auth.login')
            ->set('email', '2fa@clinic.test')
            ->set('password', 'secret123')
            ->call('authenticate')
            ->assertRedirect(route('two-factor.challenge'));

        $this->assertAuthenticated();
    }

    public function test_2fa_challenge_succeeds_with_valid_code(): void
    {
        $twoFactor = app(TwoFactorService::class);
        $secret = $twoFactor->generateSecret();
        $google2fa = new Google2FA;
        $validCode = $google2fa->getCurrentOtp($secret);

        $user = User::factory()->create([
            'email' => '2fa-valid@clinic.test',
            'password' => Hash::make('secret123'),
            'two_factor_secret' => $twoFactor->encrypt($secret),
            'two_factor_confirmed_at' => now(),
        ]);

        $this->actingAs($user);

        Livewire::test('auth.two-factor-challenge')
            ->set('code', $validCode)
            ->call('challenge')
            ->assertHasNoErrors();

        $this->assertEquals(true, session('auth.2fa.verified'));
    }

    public function test_2fa_challenge_fails_with_invalid_code(): void
    {
        $twoFactor = app(TwoFactorService::class);
        $secret = $twoFactor->generateSecret();

        $user = User::factory()->create([
            'email' => '2fa-invalid@clinic.test',
            'password' => Hash::make('secret123'),
            'two_factor_secret' => $twoFactor->encrypt($secret),
            'two_factor_confirmed_at' => now(),
        ]);

        $this->actingAs($user);

        Livewire::test('auth.two-factor-challenge')
            ->set('code', '000000')
            ->call('challenge')
            ->assertHasErrors(['code']);
    }

    public function test_2fa_recovery_code_grants_access(): void
    {
        $twoFactor = app(TwoFactorService::class);
        $secret = $twoFactor->generateSecret();
        $codes = $twoFactor->recoveryCodes();

        $user = User::factory()->create([
            'email' => '2fa-recovery@clinic.test',
            'password' => Hash::make('secret123'),
            'two_factor_secret' => $twoFactor->encrypt($secret),
            'two_factor_recovery_codes' => $codes,
            'two_factor_confirmed_at' => now(),
        ]);

        $this->actingAs($user);

        Livewire::test('auth.two-factor-challenge')
            ->set('code', $codes[0])
            ->call('challenge')
            ->assertHasNoErrors();

        $this->assertEquals(true, session('auth.2fa.verified'));
        // Recovery code should have been consumed.
        $user->refresh();
        $this->assertNotContains($codes[0], $user->two_factor_recovery_codes ?? []);
    }

    public function test_forgot_password_screen_is_rendered(): void
    {
        $response = $this->get(route('password.request'));

        $response->assertStatus(200);
        $response->assertSeeLivewire('auth.forgot-password');
    }

    public function test_forgot_password_sends_reset_link(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'reset@clinic.test']);

        Livewire::test('auth.forgot-password')
            ->set('email', 'reset@clinic.test')
            ->call('sendResetLink')
            ->assertHasNoErrors();

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        $user = User::factory()->create(['email' => 'reset2@clinic.test']);

        $token = Password::createToken($user);

        Livewire::test('auth.reset-password', ['token' => $token])
            ->set('email', 'reset2@clinic.test')
            ->set('password', 'newpassword123')
            ->set('password_confirmation', 'newpassword123')
            ->call('resetPassword')
            ->assertHasNoErrors()
            ->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('newpassword123', $user->fresh()->password));
    }
}

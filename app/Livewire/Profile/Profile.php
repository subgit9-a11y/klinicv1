<?php

declare(strict_types=1);

namespace App\Livewire\Profile;

use App\Models\User;
use App\Services\Auth\TwoFactorService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Profile extends Component
{
    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $designation = '';

    #[Validate('nullable|string|min:8|confirmed')]
    public string $password = '';

    public string $password_confirmation = '';

    // 2FA enrollment fields
    public string $two_factor_code = '';

    public string $two_factor_secret = '';

    public string $qr_svg = '';

    public ?array $recovery_codes = null;

    public function mount(): void
    {
        $user = Auth::user();
        $this->name = $user->name;
        $this->email = $user->email;
        $this->phone = (string) $user->phone;
        $this->designation = (string) $user->designation;
    }

    public function updateProfile(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'designation' => ['nullable', 'string', 'max:120'],
        ]);

        $user = Auth::user();

        // Prevent accidental email lockout — require unique email.
        $exists = User::where('email', $validated['email'])
            ->where('id', '!=', $user->id)
            ->exists();
        if ($exists) {
            throw ValidationException::withMessages(['email' => __('klinic360.auth.email_taken')]);
        }

        $user->forceFill($validated)->save();

        $this->dispatch('profile-updated');
    }

    public function updatePassword(): void
    {
        $validated = $this->validate([
            'password' => ['nullable', 'string', 'confirmed', PasswordRule::min(8)->letters()->numbers()],
        ]);

        if (empty($validated['password'])) {
            return;
        }

        $user = Auth::user();
        $user->forceFill(['password' => $validated['password']])->save();

        $this->reset(['password', 'password_confirmation']);

        $this->dispatch('password-updated');
    }

    public function enableTwoFactor(TwoFactorService $twoFactor): void
    {
        $secret = $twoFactor->generateSecret();
        $this->two_factor_secret = $secret;
        $this->qr_svg = $twoFactor->qrCodeSvg(
            company: config('app.name'),
            holder: $this->email,
            secret: $secret,
        );
        $this->recovery_codes = $twoFactor->recoveryCodes();

        $this->dispatch('two-factor-pending');
    }

    public function confirmTwoFactor(TwoFactorService $twoFactor): void
    {
        $this->validate(['two_factor_code' => 'required|string|size:6']);

        if (! $twoFactor->verify($this->two_factor_secret, $this->two_factor_code)) {
            throw ValidationException::withMessages([
                'two_factor_code' => __('klinic360.auth.invalid_2fa_code'),
            ]);
        }

        $user = Auth::user();
        $user->forceFill([
            'two_factor_secret' => $twoFactor->encrypt($this->two_factor_secret),
            'two_factor_recovery_codes' => $twoFactor->recoveryCodes(),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->reset(['two_factor_code', 'two_factor_secret', 'qr_svg', 'recovery_codes']);
        session()->put('auth.2fa.verified', true);

        $this->dispatch('two-factor-enabled');
    }

    public function disableTwoFactor(): void
    {
        $user = Auth::user();
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        session()->forget('auth.2fa.verified');

        $this->dispatch('two-factor-disabled');
    }

    public function render()
    {
        return view('livewire.profile.profile');
    }
}

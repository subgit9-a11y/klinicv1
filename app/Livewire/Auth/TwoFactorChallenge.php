<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Services\Auth\TwoFactorService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('components.layouts.guest')]
class TwoFactorChallenge extends Component
{
    // Accepts a 6-digit TOTP code or a longer recovery code (xxxxxxxxxx-xxxxxxxxxx).
    #[Validate('required|string|min:6')]
    public string $code = '';

    public function challenge(TwoFactorService $twoFactor): void
    {
        $this->validate();

        $user = Auth::user();

        if (! $user || ! $user->hasTwoFactorEnabled()) {
            $this->redirect(route('login'), navigate: false);

            return;
        }

        $secret = $twoFactor->decrypt($user->two_factor_secret);

        if (! $twoFactor->verify($secret, $this->code)) {
            // Fall back to single-use recovery codes.
            $recoveryCodes = $user->two_factor_recovery_codes ?? [];

            if (! in_array(trim($this->code), $recoveryCodes, true)) {
                throw ValidationException::withMessages([
                    'code' => __('klinic360.auth.invalid_2fa_code'),
                ]);
            }

            // A recovery code was used — remove it from the stored list.
            $user->forceFill([
                'two_factor_recovery_codes' => array_values(array_diff($recoveryCodes, [trim($this->code)])),
            ])->save();
        }

        session()->put('auth.2fa.verified', true);

        $this->redirectIntended(route('dashboard', absolute: false), navigate: false);
    }

    public function render()
    {
        return view('livewire.auth.two-factor-challenge');
    }
}

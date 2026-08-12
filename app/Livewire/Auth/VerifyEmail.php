<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.guest')]
class VerifyEmail extends Component
{
    public function resend(): void
    {
        if (request()->user()->hasVerifiedEmail()) {
            $this->redirect(route('dashboard', absolute: false), navigate: false);

            return;
        }

        request()->user()->sendEmailVerificationNotification();

        $this->dispatch('resent');
    }

    public function render()
    {
        return view('livewire.auth.verify-email');
    }
}

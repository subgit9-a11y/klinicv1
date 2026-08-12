<?php

declare(strict_types=1);

namespace App\Livewire\Patients;

use App\Models\Patient;
use Livewire\Attributes\On;
use Livewire\Component;

class Patient360 extends Component
{
    public Patient $patient;

    public string $activeTab = 'overview';

    public function mount(Patient $patient): void
    {
        $this->authorize('view', $patient);
        $this->patient = $patient;
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    #[On('patient-updated')]
    public function refreshPatient(): void
    {
        $this->patient->refresh();
    }

    public function render()
    {
        return view('livewire.patients.patient-360');
    }
}

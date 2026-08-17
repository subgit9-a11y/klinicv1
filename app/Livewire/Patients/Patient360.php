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
        $this->patient->load([
            'appointments' => fn ($q) => $q->latest('appointment_date')->limit(20),
            'consultations' => fn ($q) => $q->latest()->limit(20),
            'prescriptions' => fn ($q) => $q->latest()->limit(20),
            'treatmentBookings' => fn ($q) => $q->latest()->limit(20),
            'ipdAdmissions' => fn ($q) => $q->latest()->limit(10),
            'investigations' => fn ($q) => $q->latest()->limit(20),
            'documents' => fn ($q) => $q->latest()->limit(20),
            'followups' => fn ($q) => $q->latest('due_date')->limit(20),
            'invoices' => fn ($q) => $q->latest()->limit(20),
            'payments' => fn ($q) => $q->latest()->limit(20),
            'clinicalNotes' => fn ($q) => $q->latest()->limit(20),
            'vitals' => fn ($q) => $q->latest('recorded_at')->limit(20),
        ]);

        return view('livewire.patients.patient-360');
    }
}

<?php

declare(strict_types=1);

namespace App\Livewire\Patients;

use App\Models\Patient;
use App\Services\Patients\PatientService;
use Livewire\Component;
use Livewire\WithPagination;

class PatientList extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $showRegisterForm = false;

    public string $first_name = '';

    public string $last_name = '';

    public string $phone = '';

    public string $email = '';

    public string $gender = 'UNKNOWN';

    public ?string $dob = null;

    public string $blood_group = '';

    public string $address = '';

    public string $city = '';

    public string $state = '';

    public string $pincode = '';

    public string $abha_id = '';

    public string $allergies = '';

    public string $chronic_conditions = '';

    public string $notes = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function toggleRegisterForm(): void
    {
        $this->showRegisterForm = ! $this->showRegisterForm;
        if ($this->showRegisterForm) {
            $this->resetErrorBag();
        }
    }

    public function registerPatient(PatientService $service): void
    {
        $validated = $this->validate([
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['nullable', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:150'],
            'gender' => ['required', 'in:MALE,FEMALE,OTHER,UNKNOWN'],
            'dob' => ['nullable', 'date'],
            'blood_group' => ['nullable', 'string', 'max:8'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'pincode' => ['nullable', 'string', 'max:10'],
            'abha_id' => ['nullable', 'string', 'max:64'],
            'allergies' => ['nullable', 'string'],
            'chronic_conditions' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        try {
            $patient = $service->register(collect($validated)->filter(fn ($v) => $v !== '' && $v !== null)->all());

            $this->reset(['first_name', 'last_name', 'phone', 'email', 'gender', 'dob', 'blood_group', 'address', 'city', 'state', 'pincode', 'abha_id', 'allergies', 'chronic_conditions', 'notes', 'showRegisterForm']);

            $this->dispatch('patient-registered', patientId: $patient->id);

            session()->flash('patient-message', __('klinic360.patients.created'));
        } catch (\Illuminate\Database\QueryException $e) {
            // (tenant_id, phone) unique violation → friendly duplicate message.
            if (str_contains((string) $e->getMessage(), 'phone')) {
                $this->addError('phone', __('klinic360.patients.duplicate_phone'));
            } else {
                $this->addError('phone', $e->getMessage());
            }
        }
    }

    public function render(PatientService $service)
    {
        $patients = Patient::query()
            ->when($this->search !== '', function ($q) use ($service) {
                $term = $this->search;
                $q->where(function ($sub) use ($term) {
                    $sub->where('k360_uid', 'like', $term.'%')
                        ->orWhere('phone', 'like', '%'.$term.'%')
                        ->orWhereRaw('lower(first_name || " " || last_name) like ?', ['%'.strtolower($term).'%'])
                        ->orWhere('abha_id', 'like', $term.'%');
                });
            })
            ->orderByDesc('id')
            ->paginate(15);

        return view('livewire.patients.patient-list', ['patients' => $patients]);
    }
}

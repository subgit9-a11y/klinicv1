<?php

declare(strict_types=1);

namespace App\Livewire\Patients;

use App\Models\Patient;
use App\Services\Patients\PatientService;
use Illuminate\Database\QueryException;
use Livewire\Component;

class PatientEdit extends Component
{
    public Patient $patient;

    public string $first_name = '';

    public string $last_name = '';

    public string $phone = '';

    public ?string $email = null;

    public string $gender = 'UNKNOWN';

    public ?string $dob = null;

    public ?string $blood_group = null;

    public ?string $address = null;

    public ?string $city = null;

    public ?string $state = null;

    public ?string $pincode = null;

    public ?string $abha_id = null;

    public ?string $allergies = null;

    public ?string $chronic_conditions = null;

    public ?string $notes = null;

    public function mount(Patient $patient): void
    {
        $this->authorize('update', $patient);
        $this->patient = $patient;
        $this->fill($patient->only([
            'first_name', 'last_name', 'phone', 'email', 'gender', 'dob',
            'blood_group', 'address', 'city', 'state', 'pincode', 'abha_id',
            'allergies', 'chronic_conditions', 'notes',
        ]));
    }

    public function update(PatientService $service): void
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
            $service->update($this->patient, collect($validated)->filter(fn ($v) => $v !== '' && $v !== null)->all());

            $this->dispatch('patient-updated');

            session()->flash('patient-message', __('klinic360.patients.updated'));

            $this->redirect(route('patients.show', $this->patient), navigate: false);
        } catch (QueryException $e) {
            if (str_contains((string) $e->getMessage(), 'phone')) {
                $this->addError('phone', __('klinic360.patients.duplicate_phone'));
            } else {
                $this->addError('phone', $e->getMessage());
            }
        }
    }

    public function render()
    {
        return view('livewire.patients.patient-edit');
    }
}

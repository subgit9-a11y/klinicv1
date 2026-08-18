<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\IpdAdmission;
use App\Models\IpdWard;
use App\Services\IPD\IpdService;
use Illuminate\Http\Request;

class IpdController extends Controller
{
    public function __construct(private readonly IpdService $ipd) {}

    public function index()
    {
        $activeAdmissions = $this->ipd->activeAdmissions();
        $availableBeds = $this->ipd->availableBeds();
        $wards = IpdWard::with('rooms.beds')->orderBy('name')->get();

        return view('ipd.index', [
            'activeAdmissions' => $activeAdmissions,
            'availableBeds' => $availableBeds,
            'wards' => $wards,
        ]);
    }

    public function admit(Request $request)
    {
        $validated = $request->validate([
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'ipd_bed_id' => ['nullable', 'integer', 'exists:ipd_beds,id'],
            'admitting_doctor_id' => ['nullable', 'integer', 'exists:users,id'],
            'admission_type' => ['nullable', 'string', 'in:ROUTINE,EMERGENCY,TRANSFER'],
            'admission_reason' => ['nullable', 'string', 'max:500'],
            'provisional_diagnosis' => ['nullable', 'string', 'max:500'],
        ]);

        $admission = $this->ipd->admit($validated);

        return redirect()->route('ipd.index')->with('status', "Patient admitted — IPD #{$admission->ipd_number}.");
    }

    public function discharge(Request $request, IpdAdmission $admission)
    {
        $validated = $request->validate([
            'diagnosis' => ['nullable', 'string', 'max:500'],
            'treatment_given' => ['nullable', 'string', 'max:1000'],
            'advice_on_discharge' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->ipd->discharge($admission, $validated);

        return redirect()->route('ipd.index')->with('status', "IPD #{$admission->ipd_number} discharged.");
    }

    public function transferBed(Request $request, IpdAdmission $admission)
    {
        $validated = $request->validate([
            'ipd_bed_id' => ['required', 'integer', 'exists:ipd_beds,id'],
        ]);

        $this->ipd->transferBed($admission, $validated['ipd_bed_id']);

        return redirect()->route('ipd.index')->with('status', "IPD #{$admission->ipd_number} transferred.");
    }
}

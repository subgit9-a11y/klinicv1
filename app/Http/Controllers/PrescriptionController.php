<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Prescription;
use Illuminate\Http\Request;

class PrescriptionController extends Controller
{
    public function index(Request $request)
    {
        $query = Prescription::with(['patient', 'prescriber', 'items'])->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $prescriptions = $query->paginate(25)->appends($request->only('status'));

        return view('prescriptions.index', ['prescriptions' => $prescriptions]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'consultation_id' => ['nullable', 'integer', 'exists:consultations,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.medicine' => ['required', 'string', 'max:255'],
            'items.*.form' => ['nullable', 'string', 'max:50'],
            'items.*.strength' => ['nullable', 'string', 'max:50'],
            'items.*.dose' => ['nullable', 'string', 'max:100'],
            'items.*.frequency' => ['nullable', 'string', 'max:100'],
            'items.*.duration' => ['nullable', 'string', 'max:50'],
            'items.*.quantity' => ['nullable', 'integer', 'min:0'],
            'items.*.instructions' => ['nullable', 'string', 'max:500'],
        ]);

        $prescription = Prescription::create([
            'patient_id' => $validated['patient_id'],
            'consultation_id' => $validated['consultation_id'] ?? null,
            'user_id' => auth()->id(),
            'status' => 'ACTIVE',
            'notes' => $validated['notes'] ?? null,
            'issued_at' => now(),
        ]);

        foreach ($validated['items'] as $item) {
            $prescription->items()->create($item);
        }

        return redirect()->route('prescriptions.index')
            ->with('status', "Prescription {$prescription->id} issued.");
    }

    public function show(Prescription $prescription)
    {
        $prescription->load(['patient', 'prescriber', 'items', 'consultation']);

        return view('prescriptions.show', ['prescription' => $prescription]);
    }
}

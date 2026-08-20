<?php

declare(strict_types=1);

namespace App\Livewire\Billing;

use App\Services\Auth\Permissions;
use App\Services\Billing\CashRegisterService;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * Clinic-side cash register operations: open/close drawers, view the
 * ledger, post adjustments. Backed by CashRegisterService — double-open
 * and double-close protections live there.
 */
class CashRegisterScreen extends Component
{
    // Open form
    public ?string $open_name = null;

    public ?int $open_balance_rupees = null;

    // Close form
    public ?int $selectedRegisterId = null;

    public ?int $actual_balance_rupees = null;

    // Adjustment form
    public string $adjust_type = 'CREDIT';

    public ?int $adjust_amount_rupees = null;

    public string $adjust_reason = '';

    public function open(CashRegisterService $registers): void
    {
        $this->guard();

        $this->validate([
            'open_name' => 'nullable|string|max:120',
            'open_balance_rupees' => 'nullable|integer|min:0',
        ]);

        try {
            $register = $registers->open(auth()->user(), [
                'name' => $this->open_name,
                'opening_balance_cents' => ($this->open_balance_rupees ?? 0) * 100,
            ]);
            session()->flash('message', "Register {$register->name} opened.");
            $this->selectedRegisterId = $register->id;
        } catch (ValidationException $e) {
            session()->flash('error', collect($e->errors())->flatten()->first());
        }

        $this->reset(['open_name', 'open_balance_rupees']);
    }

    public function close(int $registerId, CashRegisterService $registers): void
    {
        $this->guard();

        $this->validate(['actual_balance_rupees' => 'nullable|integer|min:0']);

        $register = $this->findRegister($registerId);
        try {
            $register = $registers->close($register, $this->actual_balance_rupees !== null ? $this->actual_balance_rupees * 100 : null);
            session()->flash('message', "Register closed. Expected ₹".number_format($register->closing_balance_cents / 100, 2)
                .($register->variance_cents !== null ? ', variance ₹'.number_format($register->variance_cents / 100, 2) : '').'.');
        } catch (ValidationException|\DomainException $e) {
            session()->flash('error', $e->getMessage());
        }

        $this->reset(['actual_balance_rupees']);
        $this->selectedRegisterId = null;
    }

    public function postAdjustment(int $registerId, CashRegisterService $registers): void
    {
        $this->guard();

        $this->validate([
            'adjust_type' => 'required|in:CREDIT,DEBIT',
            'adjust_amount_rupees' => 'required|integer|min:1',
            'adjust_reason' => 'required|string|max:255',
        ]);

        $register = $this->findRegister($registerId);
        try {
            $registers->postAdjustment($register, $this->adjust_type, $this->adjust_amount_rupees * 100, $this->adjust_reason, auth()->user());
            session()->flash('message', 'Adjustment posted.');
        } catch (\DomainException $e) {
            session()->flash('error', $e->getMessage());
        }

        $this->reset(['adjust_amount_rupees', 'adjust_reason']);
    }

    public function select(int $registerId): void
    {
        $this->selectedRegisterId = $registerId;
    }

    private function findRegister(int $id): \App\Models\CashRegister
    {
        return \App\Models\CashRegister::findOrFail($id);
    }

    private function guard(): void
    {
        abort_unless(auth()->check() && auth()->user()->hasPermission(Permissions::CASH_REGISTER_MANAGE), 403);
    }

    public function render()
    {
        $this->guard();

        $registers = app(CashRegisterService::class);

        $openRegisters = \App\Models\CashRegister::with('user:id,name')->where('status', 'OPEN')->latest('opened_at')->get();
        $closedRegisters = \App\Models\CashRegister::with('user:id,name')->where('status', 'CLOSED')->latest('closed_at')->limit(10)->get();

        $selected = $this->selectedRegisterId !== null ? $this->findRegister($this->selectedRegisterId) : null;

        return view('livewire.billing.cash-register-screen', [
            'openRegisters' => $openRegisters,
            'closedRegisters' => $closedRegisters,
            'selected' => $selected,
            'entries' => $selected !== null ? $registers->entries($selected) : collect(),
            'selectedBalance' => $selected !== null ? $registers->balance($selected) : null,
        ])->layout('components.layouts.app');
    }
}

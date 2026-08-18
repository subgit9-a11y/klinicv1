<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\CashRegister;
use App\Models\CashRegisterEntry;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Manages the per-tenant cash register lifecycle: open → close, with
 * every payment and expense linked to the active register as a ledger
 * entry. The closing balance is computed from the entries (never trust
 * a stored running total) so the drawer can be reconciled against cash.
 *
 * Only one register per tenant+user may be OPEN at a time.
 */
class CashRegisterService
{
    /**
     * Open a cash register for the current user with an opening float.
     *
     * @param  array{name?:string, opening_balance_cents?:int}  $attributes
     */
    public function open(User $user, array $attributes = []): CashRegister
    {
        $this->assertSameTenant($user);

        $validated = Validator::validate($attributes, [
            'name' => ['nullable', 'string', 'max:120'],
            'opening_balance_cents' => ['nullable', 'integer', 'min:0'],
        ]);

        return DB::transaction(function () use ($user, $validated) {
            // Deterministic lock on the user row serialises concurrent
            // "open" requests so two clicks can't open two drawers.
            $lockedUser = User::lockForUpdate()->find($user->id);

            $alreadyOpen = CashRegister::where('user_id', $lockedUser->id)
                ->where('status', 'OPEN')
                ->lockForUpdate()
                ->exists();
            if ($alreadyOpen) {
                throw ValidationException::withMessages([
                    'register' => 'A cash register is already open for this user.',
                ]);
            }

            $opening = (int) ($validated['opening_balance_cents'] ?? 0);

            /** @var CashRegister $register */
            $register = CashRegister::create([
                'name' => $validated['name'] ?? 'Register '.$lockedUser->name,
                'user_id' => $lockedUser->id,
                'status' => 'OPEN',
                'opening_balance_cents' => $opening,
                'closing_balance_cents' => 0,
                'opened_at' => now(),
            ]);

            if ($opening > 0) {
                $this->postEntry($register, 'CREDIT', 'CASH', $opening, 'Opening float');
            }

            return $register->refresh();
        });
    }

    /**
     * Close the register, computing the closing balance from the ledger.
     */
    public function close(CashRegister $register): CashRegister
    {
        $this->assertSameTenantModel($register);

        return DB::transaction(function () use ($register) {
            $locked = CashRegister::lockForUpdate()->find($register->id);

            if ($locked->status !== 'OPEN') {
                throw new \DomainException('Only an OPEN register can be closed.');
            }

            $credits = (int) $locked->entries()->where('type', 'CREDIT')->sum('amount_cents');
            $debits = (int) $locked->entries()->where('type', 'DEBIT')->sum('amount_cents');

            $locked->update([
                'status' => 'CLOSED',
                'closing_balance_cents' => $credits - $debits,
                'closed_at' => now(),
            ]);

            return $locked->refresh();
        });
    }

    /**
     * Link a payment to the active register (called from BillingService
     * when a cash/cheque/UPI/BANK_TRANSFER payment is recorded).
     */
    public function recordPayment(Payment $payment): ?CashRegisterEntry
    {
        $registerId = $payment->cash_register_id;
        if ($registerId === null) {
            return null;
        }

        return $this->postEntry(
            CashRegister::find($registerId),
            'CREDIT',
            $payment->method,
            (int) $payment->amount_cents,
            'Payment '.$payment->payment_number,
            $payment,
            $payment->collected_by,
        );
    }

    /**
     * Link a refund to the active register as a debit.
     */
    public function recordRefund(Refund $refund): ?CashRegisterEntry
    {
        $payment = $refund->payment;
        if ($payment === null || $payment->cash_register_id === null) {
            return null;
        }

        return $this->postEntry(
            CashRegister::find($payment->cash_register_id),
            'DEBIT',
            $payment->method,
            (int) $refund->amount_cents,
            'Refund '.$refund->refund_number,
            $refund,
        );
    }

    /**
     * @return Collection<int, CashRegisterEntry>
     */
    public function entries(CashRegister $register): Collection
    {
        $this->assertSameTenantModel($register);

        return $register->entries()->latest()->get();
    }

    public function balance(CashRegister $register): int
    {
        $this->assertSameTenantModel($register);

        $credits = (int) $register->entries()->where('type', 'CREDIT')->sum('amount_cents');
        $debits = (int) $register->entries()->where('type', 'DEBIT')->sum('amount_cents');

        return $credits - $debits;
    }

    public function activeForUser(User $user): ?CashRegister
    {
        return CashRegister::where('user_id', $user->id)
            ->where('status', 'OPEN')
            ->latest()
            ->first();
    }

    /**
     * Post an expense as a debit on the given (open) register.
     */
    public function postExpense(\App\Models\Expense $expense, CashRegister $register): CashRegisterEntry
    {
        return $this->postEntry(
            $register,
            'DEBIT',
            $expense->payment_method ?? 'CASH',
            (int) $expense->amount_cents,
            'Expense: '.$expense->description,
            $expense,
            $expense->created_by,
        );
    }

    /**
     * @return Collection<int, CashRegister>
     */
    public function activeRegisters(): Collection
    {
        return CashRegister::where('status', 'OPEN')->latest('opened_at')->get();
    }

    /**
     * Internal: post a ledger entry. Bypasses the global tenant scope by
     * reading tenant_id from the register itself.
     */
    private function postEntry(
        CashRegister $register,
        string $type,
        string $method,
        int $amountCents,
        string $description,
        ?object $reference = null,
        ?int $userId = null,
    ): CashRegisterEntry {
        return CashRegisterEntry::create([
            'cash_register_id' => $register->id,
            'reference_type' => $reference?->getMorphClass(),
            'reference_id' => $reference?->id,
            'type' => $type,
            'method' => $method,
            'amount_cents' => $amountCents,
            'currency' => 'INR',
            'description' => $description,
            'user_id' => $userId ?? auth()->id(),
        ]);
    }

    private function assertSameTenant(User $user): void
    {
        $tenantId = app(TenantContext::class)->id();
        if ($tenantId !== null && $user->tenant_id !== null && $user->tenant_id !== $tenantId) {
            throw ValidationException::withMessages(['user' => 'User belongs to a different tenant.']);
        }
    }

    private function assertSameTenantModel(CashRegister $register): void
    {
        $tenantId = app(TenantContext::class)->id();
        if ($tenantId !== null && $register->tenant_id !== $tenantId) {
            throw ValidationException::withMessages(['register' => 'Cash register belongs to a different tenant.']);
        }
    }
}

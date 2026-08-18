<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\Expense;
use App\Services\Audit\AuditService;
use App\Services\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Records clinic operating expenses (rent, supplies, utilities, etc.).
 * When linked to an open cash register, a DEBIT ledger entry is posted
 * so the drawer balance reflects outgoing cash.
 */
class ExpenseService
{
    private const CATEGORIES = ['RENT', 'UTILITIES', 'SALARIES', 'SUPPLIES', 'EQUIPMENT', 'MAINTENANCE', 'MARKETING', 'MISC'];

    private const METHODS = ['CASH', 'UPI', 'CARD', 'BANK_TRANSFER', 'CHEQUE', 'OTHER'];

    public function __construct(
        private readonly AuditService $audit,
        private readonly CashRegisterService $cashRegister,
    ) {}

    /**
     * @param  array{category?:string, description:string, amount_cents:int, currency?:string, payment_method?:string, cash_register_id?:?int, expense_date?:string, receipt_path?:?string}  $attributes
     * @param  int  $createdBy  User id recording the expense.
     */
    public function create(array $attributes, int $createdBy): Expense
    {
        $tenantId = $this->requireTenant();

        $validated = Validator::validate($attributes, [
            'category' => ['nullable', 'in:'.implode(',', self::CATEGORIES)],
            'description' => ['required', 'string', 'max:255'],
            'amount_cents' => ['required', 'integer', 'min:1'],
            'currency' => ['nullable', 'string', 'max:8'],
            'payment_method' => ['nullable', 'in:'.implode(',', self::METHODS)],
            'cash_register_id' => ['nullable', 'integer', 'exists:cash_registers,id'],
            'expense_date' => ['nullable', 'date'],
            'receipt_path' => ['nullable', 'string', 'max:255'],
        ]);

        return DB::transaction(function () use ($validated, $tenantId, $createdBy) {
            $expense = Expense::create([
                'category' => $validated['category'] ?? 'MISC',
                'description' => $validated['description'],
                'amount_cents' => $validated['amount_cents'],
                'currency' => $validated['currency'] ?? 'INR',
                'payment_method' => $validated['payment_method'] ?? 'CASH',
                'cash_register_id' => $validated['cash_register_id'] ?? null,
                'created_by' => $createdBy,
                'expense_date' => $validated['expense_date'] ?? today()->toDateString(),
                'receipt_path' => $validated['receipt_path'] ?? null,
            ]);

            if ($expense->cash_register_id !== null) {
                $register = \App\Models\CashRegister::find($expense->cash_register_id);
                if ($register && $register->status !== 'OPEN') {
                    throw ValidationException::withMessages([
                        'cash_register_id' => 'Linked cash register is not open.',
                    ]);
                }
                $this->cashRegister->postExpense($expense, $register);
            }

            $this->audit->record('expense.recorded', 'billing', ['after' => ['amount_cents' => $validated['amount_cents'], 'category' => $expense->category]], $expense);

            return $expense->refresh();
        });
    }

    /**
     * @return Collection<int, Expense>
     */
    public function list(?Carbon $from = null, ?Carbon $to = null, ?string $category = null): Collection
    {
        $tenantId = $this->requireTenant();

        return Expense::query()
            ->where('tenant_id', $tenantId)
            ->when($from, fn ($q, $d) => $q->whereDate('expense_date', '>=', $d))
            ->when($to, fn ($q, $d) => $q->whereDate('expense_date', '<=', $d))
            ->when($category, fn ($q, $c) => $q->where('category', $c))
            ->latest('expense_date')
            ->get();
    }

    /**
     * Sum expenses in a period (for the daily/monthly cash reconciliation).
     */
    public function totalForPeriod(Carbon $from, Carbon $to): int
    {
        $tenantId = $this->requireTenant();

        return (int) Expense::where('tenant_id', $tenantId)
            ->whereBetween('expense_date', [$from->toDateString(), $to->toDateString()])
            ->sum('amount_cents');
    }

    private function requireTenant(): int
    {
        $tenantId = app(TenantContext::class)->id();
        if ($tenantId === null) {
            throw ValidationException::withMessages(['tenant' => 'No active tenant context.']);
        }

        return $tenantId;
    }
}

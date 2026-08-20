<?php

declare(strict_types=1);

namespace App\Livewire\Billing;

use App\Services\Auth\Permissions;
use App\Services\Billing\ExpenseService;
use Livewire\Component;

/**
 * Clinic-side expense tracking. Backed by ExpenseService.
 */
class ExpenseTracker extends Component
{
    public const CATEGORIES = ['RENT', 'UTILITIES', 'SALARIES', 'SUPPLIES', 'EQUIPMENT', 'MAINTENANCE', 'MARKETING', 'MISC'];

    public const METHODS = ['CASH', 'UPI', 'CARD', 'BANK_TRANSFER', 'CHEQUE', 'OTHER'];

    public bool $showForm = false;

    public string $description = '';

    public string $category = 'MISC';

    public string $payment_method = 'CASH';

    public ?int $amount_rupees = null;

    public ?string $expense_date = null;

    public string $categoryFilter = '';

    public function addExpense(ExpenseService $expenses): void
    {
        $this->guard();

        $this->validate([
            'description' => 'required|string|max:255',
            'category' => 'required|in:'.implode(',', self::CATEGORIES),
            'payment_method' => 'required|in:'.implode(',', self::METHODS),
            'amount_rupees' => 'required|integer|min:1',
            'expense_date' => 'nullable|date',
        ]);

        $expenses->create([
            'description' => $this->description,
            'category' => $this->category,
            'payment_method' => $this->payment_method,
            'amount_cents' => $this->amount_rupees * 100,
            'expense_date' => $this->expense_date,
        ], auth()->id());

        session()->flash('message', 'Expense recorded.');
        $this->reset(['description', 'amount_rupees', 'expense_date']);
        $this->showForm = false;
    }

    private function guard(): void
    {
        abort_unless(auth()->check() && auth()->user()->hasPermission(Permissions::BILLING_VIEW), 403);
    }

    public function render()
    {
        $this->guard();

        $service = app(ExpenseService::class);
        $list = $service->list(category: $this->categoryFilter !== '' ? $this->categoryFilter : null);

        return view('livewire.billing.expense-tracker', [
            'expenses' => $list,
            'totalCents' => $list->sum('amount_cents'),
        ])->layout('components.layouts.app');
    }
}

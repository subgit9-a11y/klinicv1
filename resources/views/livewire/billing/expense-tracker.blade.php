<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Expenses</h1>
            <p class="text-sm text-gray-600">Clinic spending by category and payment method.</p>
        </div>
        <button wire:click="$toggle('showForm')" class="px-4 py-2 bg-brand-600 text-white rounded-md text-sm font-medium hover:bg-brand-700">
            {{ $showForm ? 'Close' : '+ New Expense' }}
        </button>
    </div>

    @if (session('message'))
        <div class="mb-4 p-3 bg-green-100 text-green-700 rounded">{{ session('message') }}</div>
    @endif

    @if ($showForm)
        <div class="mb-6 bg-white p-6 rounded-lg shadow-sm border border-gray-200">
            <h2 class="text-lg font-semibold mb-4">New expense</h2>
            <form wire:submit="addExpense" class="grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <input wire:model="description" type="text" class="w-full rounded border-gray-300 shadow-sm" placeholder="Monthly electricity bill">
                    @error('description') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                    <select wire:model="category" class="w-full rounded border-gray-300 shadow-sm">
                        @foreach (\App\Livewire\Billing\ExpenseTracker::CATEGORIES as $cat)
                            <option value="{{ $cat }}">{{ ucfirst(strtolower($cat)) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Payment method</label>
                    <select wire:model="payment_method" class="w-full rounded border-gray-300 shadow-sm">
                        @foreach (\App\Livewire\Billing\ExpenseTracker::METHODS as $method)
                            <option value="{{ $method }}">{{ ucfirst(strtolower($method)) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Amount (₹)</label>
                    <input wire:model="amount_rupees" type="number" min="1" class="w-full rounded border-gray-300 shadow-sm">
                    @error('amount_rupees') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Date (optional)</label>
                    <input wire:model="expense_date" type="date" class="w-full rounded border-gray-300 shadow-sm">
                </div>
                <div class="col-span-2">
                    <button type="submit" class="px-4 py-2 bg-brand-600 text-white rounded-md text-sm font-medium hover:bg-brand-700">Record expense</button>
                </div>
            </form>
        </div>
    @endif

    <div class="mb-4 flex items-center gap-4">
        <select wire:model.live="categoryFilter" class="rounded border-gray-300 shadow-sm">
            <option value="">All categories</option>
            @foreach (\App\Livewire\Billing\ExpenseTracker::CATEGORIES as $cat)
                <option value="{{ $cat }}">{{ ucfirst(strtolower($cat)) }}</option>
            @endforeach
        </select>
        <div class="text-sm text-gray-600">Total: <span class="font-semibold">₹{{ number_format($totalCents / 100, 2) }}</span></div>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50"><tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Method</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Amount</th>
            </tr></thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($expenses as $expense)
                    <tr>
                        <td class="px-4 py-3 text-sm text-gray-500">{{ $expense->expense_date?->format('d M Y') ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $expense->description }}</td>
                        <td class="px-4 py-3 text-sm text-gray-700">{{ $expense->category }}</td>
                        <td class="px-4 py-3 text-sm text-gray-700">{{ $expense->payment_method }}</td>
                        <td class="px-4 py-3 text-sm text-gray-900 text-right font-medium">₹{{ number_format($expense->amount_cents / 100, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-gray-500">No expenses recorded.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

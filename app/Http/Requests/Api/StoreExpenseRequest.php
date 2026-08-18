<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category' => ['nullable', 'in:RENT,UTILITIES,SALARIES,SUPPLIES,EQUIPMENT,MAINTENANCE,MARKETING,MISC'],
            'description' => ['required', 'string', 'max:255'],
            'amount_cents' => ['required', 'integer', 'min:1'],
            'currency' => ['nullable', 'string', 'max:8'],
            'payment_method' => ['nullable', 'in:CASH,UPI,CARD,BANK_TRANSFER,CHEQUE,OTHER'],
            'cash_register_id' => ['nullable', 'integer', 'exists:cash_registers,id'],
            'expense_date' => ['nullable', 'date'],
            'receipt_path' => ['nullable', 'string', 'max:255'],
        ];
    }
}

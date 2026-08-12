<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'category', 'description', 'amount_cents', 'currency',
        'payment_method', 'cash_register_id', 'created_by', 'expense_date',
        'receipt_path',
    ];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'expense_date' => 'date',
        ];
    }
}

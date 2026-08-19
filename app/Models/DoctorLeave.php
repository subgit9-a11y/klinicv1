<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DoctorLeave extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id', 'user_id', 'start_date', 'end_date',
        'reason', 'type', 'is_approved',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_approved' => 'boolean',
    ];

    /**
     * Scope to approved leaves overlapping the given date (inclusive range).
     * Uses whereDate() so the comparison is correct on SQLite, which stores
     * date columns as full 'Y-m-d 00:00:00' text (a plain string `<=` fails).
     */
    public function scopeOnDate(\Illuminate\Database\Eloquent\Builder $q, \Illuminate\Support\Carbon|string $date): void
    {
        $q->where('is_approved', true)
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

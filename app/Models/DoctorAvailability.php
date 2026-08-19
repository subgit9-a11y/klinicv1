<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DoctorAvailability extends Model
{
    use BelongsToTenant, HasFactory;

    protected $table = 'doctor_availability';

    protected $fillable = [
        'tenant_id', 'user_id', 'day_of_week', 'start_time', 'end_time',
        'break_start_time', 'break_end_time', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

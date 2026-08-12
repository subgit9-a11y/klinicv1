<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class AppointmentToken extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'appointment_id', 'user_id', 'token_number',
        'appointment_date', 'status', 'called_at',
    ];

    protected function casts(): array
    {
        return [
            'appointment_date' => 'date',
            'called_at' => 'datetime',
        ];
    }

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    public function doctor()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

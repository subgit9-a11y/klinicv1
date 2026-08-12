<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class AppointmentStatusHistory extends Model
{
    use BelongsToTenant;

    protected $table = 'appointment_status_history';

    protected $fillable = ['tenant_id', 'appointment_id', 'status', 'changed_by', 'note'];

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }
}

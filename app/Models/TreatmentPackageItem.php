<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TreatmentPackageItem extends Model
{
    protected $fillable = ['treatment_package_id', 'treatment_service_id', 'quantity'];

    protected function casts(): array
    {
        return ['quantity' => 'integer'];
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(TreatmentPackage::class, 'treatment_package_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(TreatmentService::class, 'treatment_service_id');
    }
}

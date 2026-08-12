<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlanFeature extends Model
{
    protected $fillable = [
        'plan_id', 'feature_key', 'enabled', 'limit_value', 'limit_unit',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'limit_value' => 'integer',
        ];
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }
}

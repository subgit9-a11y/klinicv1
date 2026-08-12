<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    use HasFactory;
    protected $fillable = [
        'code', 'name', 'description', 'price_cents', 'currency', 'billing_cycle',
        'is_active', 'max_users', 'max_doctors', 'max_patients',
        'max_appointments_per_day', 'ai_request_limit_per_day',
        'ipd_enabled', 'treatments_enabled', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'price_cents' => 'integer',
            'is_active' => 'boolean',
            'max_users' => 'integer',
            'max_doctors' => 'integer',
            'ipd_enabled' => 'boolean',
            'treatments_enabled' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function features()
    {
        return $this->hasMany(PlanFeature::class);
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }
}

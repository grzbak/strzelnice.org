<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShootingRangePackage extends Model
{
    protected $table = 'shooting_range_packages';

    protected $fillable = [
        'shooting_range_id',
        'name',
        'description',
        'type',
        'period_months',
        'bonus_months',
        'value',
        'price_gross',
        'vat_rate',
        'currency',
        'valid_from',
        'valid_to',
        'available_from',
        'available_to',
        'max_uses_total',
        'max_uses_per_shooting_range',
        'is_active',
        'is_public',
        'requires_superadmin_approval',
        'created_by_user_id',
    ];

    protected $casts = [
        'shooting_range_id' => 'integer',
        'period_months' => 'integer',
        'bonus_months' => 'integer',
        'value' => 'decimal:2',
        'price_gross' => 'decimal:2',
        'vat_rate' => 'decimal:2',
        'valid_from' => 'date',
        'valid_to' => 'date',
        'available_from' => 'date',
        'available_to' => 'date',
        'is_active' => 'boolean',
        'is_public' => 'boolean',
        'requires_superadmin_approval' => 'boolean',
    ];

    public function shootingRange()
    {
        return $this->belongsTo(ShootingRange::class);
    }

    public function orders()
    {
        return $this->hasMany(PackageOrder::class, 'shooting_range_package_id');
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->name ?: $this->period_months.' mies.';
    }

    public function getEffectivePriceAttribute(): string
    {
        return (string) ($this->price_gross ?? $this->value);
    }
}

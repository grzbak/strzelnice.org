<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShootingRangePackage extends Model
{
    protected $table = 'shooting_range_packages';

    protected $fillable = [
        'shooting_range_id',
        'period_months',
        'value',
        'valid_from',
    ];

    protected $casts = [
        'shooting_range_id' => 'integer',
        'period_months' => 'integer',
        'value' => 'decimal:2',
        'valid_from' => 'date',
    ];

    public function shootingRange()
    {
        return $this->belongsTo(ShootingRange::class);
    }
}

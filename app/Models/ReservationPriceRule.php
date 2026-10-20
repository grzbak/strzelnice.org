<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReservationPriceRule extends Model
{
    protected $fillable = [
        'shooting_range_id',
        'lane_id',
        'name',
        'weekdays',
        'time_from',
        'time_to',
        'valid_from',
        'valid_to',
        'price_per_position_30min',
        'dynamic_price_per_position_30min',
        'currency',
        'priority',
        'is_active',
        'note',
    ];

    protected $casts = [
        'weekdays' => 'array',
        'valid_from' => 'date',
        'valid_to' => 'date',
        'price_per_position_30min' => 'decimal:2',
        'dynamic_price_per_position_30min' => 'decimal:2',
        'priority' => 'integer',
        'is_active' => 'boolean',
    ];

    public function shootingRange(): BelongsTo
    {
        return $this->belongsTo(ShootingRange::class);
    }

    public function lane(): BelongsTo
    {
        return $this->belongsTo(Lane::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReservationPricePromotion extends Model
{
    public const TYPE_PERCENT = 'percent';

    public const TYPE_AMOUNT = 'amount';

    protected $fillable = [
        'shooting_range_id',
        'lane_id',
        'name',
        'discount_type',
        'discount_value',
        'weekdays',
        'time_from',
        'time_to',
        'valid_from',
        'valid_to',
        'priority',
        'is_active',
        'note',
    ];

    protected $casts = [
        'weekdays' => 'array',
        'discount_value' => 'decimal:2',
        'valid_from' => 'date',
        'valid_to' => 'date',
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

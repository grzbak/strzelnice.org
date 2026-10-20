<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShootingRangePaymentAccount extends Model
{
    public const STATUS_NOT_CONFIGURED = 'not_configured';

    public const STATUS_READY = 'ready';

    public const STATUS_INVALID = 'invalid';

    protected $fillable = [
        'shooting_range_id',
        'p24_enabled',
        'p24_sandbox',
        'p24_merchant_id',
        'p24_pos_id',
        'p24_crc',
        'p24_api_key',
        'currency',
        'reservation_payment_time_limit_minutes',
        'status',
        'last_tested_at',
        'last_test_success',
        'last_test_message',
    ];

    protected $casts = [
        'p24_enabled' => 'boolean',
        'p24_sandbox' => 'boolean',
        'last_tested_at' => 'datetime',
        'last_test_success' => 'boolean',
        'reservation_payment_time_limit_minutes' => 'integer',
    ];

    public function shootingRange(): BelongsTo
    {
        return $this->belongsTo(ShootingRange::class);
    }
}

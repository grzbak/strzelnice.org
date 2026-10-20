<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingCharge extends Model
{
    public const STATUS_UNPAID = 'unpaid';

    public const STATUS_PAID = 'paid';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'shooting_range_id',
        'package_order_id',
        'payment_id',
        'type',
        'status',
        'amount',
        'currency',
        'period_from',
        'period_to',
        'due_at',
        'paid_at',
        'description',
        'note',
        'created_by_user_id',
        'settled_by_user_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'period_from' => 'date',
        'period_to' => 'date',
        'due_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function shootingRange(): BelongsTo
    {
        return $this->belongsTo(ShootingRange::class);
    }

    public function packageOrder(): BelongsTo
    {
        return $this->belongsTo(PackageOrder::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}

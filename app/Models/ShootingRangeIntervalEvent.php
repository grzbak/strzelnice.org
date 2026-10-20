<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShootingRangeIntervalEvent extends Model
{
    public const TYPE_ORDER_CREATED = 'order_created';

    public const TYPE_ORDER_PAID = 'order_paid';

    public const TYPE_ORDER_CANCELLED = 'order_cancelled';

    public const TYPE_ORDER_EXPIRED = 'order_expired';

    public const TYPE_MANUAL_EXTENDED = 'manual_extended';

    public const TYPE_INTERVAL_RECALCULATED = 'interval_recalculated';

    public const SOURCE_PACKAGE_ORDER = 'package_order';

    public const SOURCE_MANUAL = 'manual';

    protected $fillable = [
        'shooting_range_interval_id',
        'shooting_range_id',
        'package_order_id',
        'payment_id',
        'created_by_user_id',
        'type',
        'source',
        'date_from',
        'date_to',
        'previous_start_date',
        'previous_end_date',
        'new_start_date',
        'new_end_date',
        'amount',
        'currency',
        'payment_status',
        'payment_method',
        'note',
    ];

    protected $casts = [
        'date_from' => 'date',
        'date_to' => 'date',
        'previous_start_date' => 'date',
        'previous_end_date' => 'date',
        'new_start_date' => 'date',
        'new_end_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function interval(): BelongsTo
    {
        return $this->belongsTo(ShootingRangeInterval::class, 'shooting_range_interval_id');
    }

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

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_ORDER_CREATED => 'Utworzono zamówienie',
            self::TYPE_ORDER_PAID => 'Rozliczono płatność',
            self::TYPE_ORDER_CANCELLED => 'Anulowano zamówienie',
            self::TYPE_ORDER_EXPIRED => 'Przeterminowano zamówienie',
            self::TYPE_MANUAL_EXTENDED => 'Ręczne rozszerzenie',
            self::TYPE_INTERVAL_RECALCULATED => 'Przeliczono przedział',
            default => $this->type,
        };
    }

    public function getSourceLabelAttribute(): string
    {
        return match ($this->source) {
            self::SOURCE_PACKAGE_ORDER => 'Abonament',
            self::SOURCE_MANUAL => 'Ręcznie',
            default => $this->source,
        };
    }
}

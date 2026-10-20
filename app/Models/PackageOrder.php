<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PackageOrder extends Model
{
    public const STATUS_CONDITIONAL_ACTIVE = 'conditional_active';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_CANCELLED = 'cancelled';

    public const PAYMENT_PENDING = 'pending';

    public const PAYMENT_PAID = 'paid';

    public const PAYMENT_FAILED = 'failed';

    public const PAYMENT_CANCELLED = 'cancelled';

    public const METHOD_P24 = 'p24';

    public const METHOD_BANK_TRANSFER = 'bank_transfer';

    public const METHOD_CASH = 'cash';

    public const METHOD_FREE = 'free';

    protected $fillable = [
        'shooting_range_id',
        'shooting_range_package_id',
        'ordered_by_user_id',
        'created_by_user_id',
        'activated_by_user_id',
        'cancelled_by_user_id',
        'shooting_range_interval_id',
        'status',
        'payment_method',
        'payment_status',
        'amount_gross',
        'currency',
        'period_months',
        'bonus_months',
        'starts_at',
        'ends_at',
        'payment_due_at',
        'paid_at',
        'activated_at',
        'expired_at',
        'cancelled_at',
        'notes',
        'internal_note',
        'payment_note',
        'manual_reason',
    ];

    protected $casts = [
        'amount_gross' => 'decimal:2',
        'period_months' => 'integer',
        'bonus_months' => 'integer',
        'starts_at' => 'date',
        'ends_at' => 'date',
        'payment_due_at' => 'datetime',
        'paid_at' => 'datetime',
        'activated_at' => 'datetime',
        'expired_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function shootingRange(): BelongsTo
    {
        return $this->belongsTo(ShootingRange::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(ShootingRangePackage::class, 'shooting_range_package_id');
    }

    public function orderedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ordered_by_user_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function interval(): BelongsTo
    {
        return $this->belongsTo(ShootingRangeInterval::class, 'shooting_range_interval_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function intervalEvents(): HasMany
    {
        return $this->hasMany(ShootingRangeIntervalEvent::class);
    }

    public function billingCharges(): HasMany
    {
        return $this->hasMany(BillingCharge::class);
    }

    public function getIsPaidAttribute(): bool
    {
        return $this->payment_status === self::PAYMENT_PAID;
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_CONDITIONAL_ACTIVE => 'Aktywny warunkowo',
            self::STATUS_ACTIVE => 'Aktywny',
            self::STATUS_EXPIRED => 'Wygasły',
            self::STATUS_CANCELLED => 'Usunięty',
            default => $this->status,
        };
    }

    public function getPaymentStatusLabelAttribute(): string
    {
        return match ($this->payment_status) {
            self::PAYMENT_PENDING => 'Oczekuje na płatność',
            self::PAYMENT_PAID => 'Opłacone',
            self::PAYMENT_FAILED => 'Nieopłacone / wygasłe',
            self::PAYMENT_CANCELLED => 'Anulowane',
            default => $this->payment_status,
        };
    }

    public function getPaymentStatusBadgeClassAttribute(): string
    {
        return match ($this->payment_status) {
            self::PAYMENT_PAID => 'success',
            self::PAYMENT_FAILED => 'danger',
            self::PAYMENT_CANCELLED => 'secondary',
            default => 'warning text-dark',
        };
    }

    public function getPaymentMethodLabelAttribute(): string
    {
        return match ($this->payment_method) {
            self::METHOD_P24 => 'Przelewy24',
            self::METHOD_BANK_TRANSFER => 'Przelew tradycyjny',
            self::METHOD_CASH => 'Gotówka',
            self::METHOD_FREE => 'Bez płatności',
            default => $this->payment_method,
        };
    }
}

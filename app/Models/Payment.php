<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Payment extends Model
{
    public const STATUS_CREATED = 'created';

    public const STATUS_REGISTERED = 'registered';

    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_FAILED = 'failed';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_REFUND_PENDING = 'refund_pending';

    public const STATUS_PARTIALLY_REFUNDED = 'partially_refunded';

    public const STATUS_REFUNDED = 'refunded';

    public const STATUS_REFUND_FAILED = 'refund_failed';

    protected $fillable = [
        'payable_type',
        'payable_id',
        'package_order_id',
        'provider',
        'method',
        'status',
        'amount',
        'amount_minor',
        'refunded_amount_minor',
        'refund_requested_amount_minor',
        'currency',
        'description',
        'payer_email',
        'payer_name',
        'p24_session_id',
        'p24_token',
        'p24_order_id',
        'p24_method',
        'url_return',
        'url_status',
        'registered_at',
        'verified_at',
        'failed_at',
        'refunded_at',
        'refund_request_id',
        'refunds_uuid',
        'refund_error',
        'expires_at',
        'confirmed_by_user_id',
        'confirmed_at',
        'confirmation_note',
        'note',
        'raw_register_response',
        'raw_refund_response',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'amount_minor' => 'integer',
        'refunded_amount_minor' => 'integer',
        'refund_requested_amount_minor' => 'integer',
        'p24_order_id' => 'integer',
        'p24_method' => 'integer',
        'registered_at' => 'datetime',
        'verified_at' => 'datetime',
        'failed_at' => 'datetime',
        'refunded_at' => 'datetime',
        'expires_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'raw_register_response' => 'array',
        'raw_refund_response' => 'array',
    ];

    public function getP24PanelUrlAttribute(): ?string
    {
        if (! $this->p24_order_id) {
            return null;
        }

        $configuration = SystemConfiguration::activeConfiguration();
        $baseUrl = $configuration?->p24_sandbox
            ? 'https://sandbox.przelewy24.pl'
            : 'https://secure.przelewy24.pl';

        return $baseUrl.'/panel/transakcja.php?id='.$this->p24_order_id;
    }

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    public function packageOrder(): BelongsTo
    {
        return $this->belongsTo(PackageOrder::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(PaymentEvent::class);
    }
}

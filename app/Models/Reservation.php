<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Reservation extends Model
{
    use SoftDeletes;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_NO_SHOW = 'no_show';

    public const STATUS_PAYMENT_EXPIRED = 'payment_expired';

    public const PAYMENT_NOT_REQUIRED = 'not_required';

    public const PAYMENT_PENDING = 'pending';

    public const PAYMENT_PAID = 'paid';

    public const PAYMENT_FAILED = 'failed';

    public const PAYMENT_EXPIRED = 'expired';

    public const PAYMENT_MANUAL = 'manual';

    public const PAYMENT_REFUNDED = 'refunded';

    public const SOURCE_ONLINE_ACCOUNT = 'online_account';

    public const SOURCE_STAFF_ACCOUNT = 'staff_account';

    public const SOURCE_STAFF_GUEST = 'staff_guest';

    protected $table = 'reservations';

    protected $fillable = [
        'user_id',
        'mod_id',
        'customer_user_id',
        'customer_name',
        'customer_email',
        'customer_phone',
        'created_by_user_id',
        'source',
        'shooting_range_id',
        'line_id',
        'start',
        'end',
        'positions_count',
        'is_dynamic_shooting',
        'has_shooting_leader_license',
        'shooting_leader_license_number',
        'status',
        'payment_required',
        'payment_status',
        'payment_amount',
        'payment_currency',
        'payment_expires_at',
        'paid_at',
        'payment_method',
        'approved_by_user_id',
        'approved_at',
        'cancelled_by_user_id',
        'cancelled_at',
        'finalized_by_user_id',
        'finalized_at',
        'cancellation_reason',
        'notes',
        'today_reminder_sent_at',
        'deleted_by_user_id',
        'deletion_reason',
    ];

    protected $casts = [
        'start' => 'datetime',
        'end' => 'datetime',
        'positions_count' => 'integer',
        'is_dynamic_shooting' => 'boolean',
        'has_shooting_leader_license' => 'boolean',
        'payment_required' => 'boolean',
        'payment_amount' => 'decimal:2',
        'payment_expires_at' => 'datetime',
        'paid_at' => 'datetime',
        'approved_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'finalized_at' => 'datetime',
        'today_reminder_sent_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function moderator()
    {
        return $this->belongsTo(User::class, 'mod_id');
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_user_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function canceller()
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }

    public function finalizer()
    {
        return $this->belongsTo(User::class, 'finalized_by_user_id');
    }

    public function deleter()
    {
        return $this->belongsTo(User::class, 'deleted_by_user_id');
    }

    public function shootingRange()
    {
        return $this->belongsTo(ShootingRange::class);
    }

    public function lane()
    {
        return $this->belongsTo(Lane::class, 'line_id');
    }

    public function payments()
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    public function changeRequests()
    {
        return $this->hasMany(ReservationChangeRequest::class);
    }

    public function pendingChangeRequests()
    {
        return $this->changeRequests()
            ->where('status', ReservationChangeRequest::STATUS_PENDING);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isFinalized(): bool
    {
        return in_array($this->status, [
            self::STATUS_COMPLETED,
            self::STATUS_NO_SHOW,
            self::STATUS_PAYMENT_EXPIRED,
        ], true);
    }

    public function canBeEdited(): bool
    {
        return $this->start->isFuture()
            && in_array($this->status, [
                self::STATUS_PENDING,
                self::STATUS_APPROVED,
            ], true);
    }

    public function canBeEditedByCustomer(): bool
    {
        return $this->start->isFuture() && ! $this->isFinalized();
    }

    public function isPaid(): bool
    {
        return $this->payment_status === self::PAYMENT_PAID;
    }

    public function isPaymentVerificationPending(): bool
    {
        if (! $this->payment_required || $this->payment_status !== self::PAYMENT_PENDING) {
            return false;
        }

        return $this->payments()
            ->where('method', 'p24')
            ->whereNotNull('p24_order_id')
            ->whereIn('status', [
                Payment::STATUS_CREATED,
                Payment::STATUS_REGISTERED,
                Payment::STATUS_PENDING,
            ])
            ->exists();
    }

    public function hasPendingChangeRequest(): bool
    {
        return $this->pendingChangeRequests()->exists();
    }

    public function canBeHiddenByStaff(): bool
    {
        if (in_array($this->status, [
            self::STATUS_COMPLETED,
            self::STATUS_NO_SHOW,
        ], true)) {
            return false;
        }

        if ($this->isPaid()) {
            return $this->isCancelled()
                && $this->payment_status === self::PAYMENT_REFUNDED;
        }

        return in_array($this->status, [
            self::STATUS_PENDING,
            self::STATUS_CANCELLED,
            self::STATUS_PAYMENT_EXPIRED,
        ], true);
    }
}

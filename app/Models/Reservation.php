<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reservation extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_CANCELLED = 'cancelled';

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
        'status',
        'approved_by_user_id',
        'approved_at',
        'cancelled_by_user_id',
        'cancelled_at',
        'cancellation_reason',
        'notes',
    ];

    protected $casts = [
        'start' => 'datetime',
        'end' => 'datetime',
        'positions_count' => 'integer',
        'is_dynamic_shooting' => 'boolean',
        'approved_at' => 'datetime',
        'cancelled_at' => 'datetime',
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

    public function shootingRange()
    {
        return $this->belongsTo(ShootingRange::class);
    }

    public function lane()
    {
        return $this->belongsTo(Lane::class, 'line_id');
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
}

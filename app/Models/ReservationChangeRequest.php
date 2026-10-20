<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReservationChangeRequest extends Model
{
    public const TYPE_CHANGE = 'change';

    public const TYPE_CANCEL = 'cancel';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'reservation_id',
        'requested_by_user_id',
        'decided_by_user_id',
        'type',
        'status',
        'old_lane_id',
        'new_lane_id',
        'old_start',
        'old_end',
        'new_start',
        'new_end',
        'old_positions_count',
        'new_positions_count',
        'old_is_dynamic_shooting',
        'new_is_dynamic_shooting',
        'old_has_shooting_leader_license',
        'new_has_shooting_leader_license',
        'old_shooting_leader_license_number',
        'new_shooting_leader_license_number',
        'customer_note',
        'decision_note',
        'decided_at',
    ];

    protected $casts = [
        'old_start' => 'datetime',
        'old_end' => 'datetime',
        'new_start' => 'datetime',
        'new_end' => 'datetime',
        'old_positions_count' => 'integer',
        'new_positions_count' => 'integer',
        'old_is_dynamic_shooting' => 'boolean',
        'new_is_dynamic_shooting' => 'boolean',
        'old_has_shooting_leader_license' => 'boolean',
        'new_has_shooting_leader_license' => 'boolean',
        'decided_at' => 'datetime',
    ];

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function decider()
    {
        return $this->belongsTo(User::class, 'decided_by_user_id');
    }

    public function oldLane()
    {
        return $this->belongsTo(Lane::class, 'old_lane_id');
    }

    public function newLane()
    {
        return $this->belongsTo(Lane::class, 'new_lane_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}

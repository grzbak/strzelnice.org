<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReservationArchive extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'original_reservation_id',
        'user_id',
        'mod_id',
        'customer_user_id',
        'customer_name',
        'customer_email',
        'customer_phone',
        'created_by_user_id',
        'source',
        'shooting_range_id',
        'shooting_range_name',
        'shooting_range_town',
        'line_id',
        'lane_name',
        'lane_length',
        'start',
        'end',
        'positions_count',
        'is_dynamic_shooting',
        'has_shooting_leader_license',
        'shooting_leader_license_number',
        'status',
        'approved_by_user_id',
        'approved_at',
        'cancelled_by_user_id',
        'cancelled_at',
        'finalized_by_user_id',
        'finalized_at',
        'cancellation_reason',
        'notes',
        'reservation_created_at',
        'reservation_updated_at',
        'archived_at',
    ];

    protected $casts = [
        'start' => 'datetime',
        'end' => 'datetime',
        'positions_count' => 'integer',
        'is_dynamic_shooting' => 'boolean',
        'has_shooting_leader_license' => 'boolean',
        'approved_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'finalized_at' => 'datetime',
        'reservation_created_at' => 'datetime',
        'reservation_updated_at' => 'datetime',
        'archived_at' => 'datetime',
    ];
}

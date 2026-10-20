<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShootingRangeIntervalArchive extends Model
{
    protected $table = 'shooting_range_interval_archive';

    protected $fillable = [
        'shooting_range_interval_id',
        'shooting_range_id',
        'package_order_id',
        'payment_id',
        'start_date',
        'end_date',
        'operator_insert_id',
        'operator_update_id',
        'value',
        'datePaid',
        'payment_due_date',
        'is_active',
        'source',
        'subscription_status',
        'activated_by_user_id',
        'activated_at',
        'blocked_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'datePaid' => 'datetime',
        'payment_due_date' => 'date',
        'value' => 'decimal:2',
        'is_active' => 'boolean',
        'activated_at' => 'datetime',
        'blocked_at' => 'datetime',
    ];

    public function shootingRange()
    {
        return $this->belongsTo(ShootingRange::class);
    }

    public function operatorInsert()
    {
        return $this->belongsTo(User::class, 'operator_insert_id');
    }

    public function operatorUpdate()
    {
        return $this->belongsTo(User::class, 'operator_update_id');
    }

    public function interval()
    {
        return $this->belongsTo(ShootingRangeInterval::class, 'shooting_range_interval_id');
    }

    public function packageOrder()
    {
        return $this->belongsTo(PackageOrder::class);
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }
}

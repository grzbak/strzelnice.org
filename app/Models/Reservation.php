<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reservation extends Model
{
    protected $table = 'reservations';

    protected $fillable = [
        'user_id',
        'mod_id',
        'shooting_range_id',
        'line_id',
        'start',
        'end',
        'positions_count',
        'status',
    ];

    protected $casts = [
        'start' => 'datetime',
        'positions_count' => 'integer',
        'end' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function moderator()
    {
        return $this->belongsTo(User::class, 'mod_id');
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
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }
}
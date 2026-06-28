<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lane extends Model
{
    protected $fillable = [
        'shooting_range_id',
        'name',
        'length',
        'numberOfPositions',
        'allows_dynamic_shooting',
        'is_archived',
        'description',
    ];

    protected $casts = [
        'allows_dynamic_shooting' => 'boolean',
        'is_archived' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_archived', false);
    }

    public function shootingRange()
    {
        return $this->belongsTo(ShootingRange::class);
    }

    public function reservations()
    {
        return $this->hasMany(Reservation::class, 'line_id');
    }
}

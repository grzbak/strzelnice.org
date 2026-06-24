<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShootingRangeHoliday extends Model
{
    protected $fillable = [
        'shooting_range_id',
        'date',
        'name',
        'description',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
    ];

    public function shootingRange()
    {
        return $this->belongsTo(ShootingRange::class);
    }
}
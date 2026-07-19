<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShootingRangeOpeningHour extends Model
{
    protected $table = 'shooting_range_opening_hours';

    protected $fillable = [
        'shooting_range_id',
        'day_of_week',
        'opens_at',
        'closes_at',
    ];

    protected $casts = [
        'opens_at' => 'datetime:H:i',
        'closes_at' => 'datetime:H:i',
    ];

    /**
     * Relacja do strzelnicy
     */
    public function shootingRange(): BelongsTo
    {
        return $this->belongsTo(ShootingRange::class);
    }

    /**
     * Nazwy dni tygodnia
     */
    public function getDayNameAttribute(): string
    {
        return match ($this->day_of_week) {
            1 => 'Poniedziałek',
            2 => 'Wtorek',
            3 => 'Środa',
            4 => 'Czwartek',
            5 => 'Piątek',
            6 => 'Sobota',
            7 => 'Niedziela',
            default => 'Nieznany',
        };
    }
}

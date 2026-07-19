<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class ShootingRangeInterval extends Model
{
    protected $table = 'shooting_range_interval';

    protected $fillable = [
        'shooting_range_id',
        'start_date',
        'end_date',
        'operator_insert_id',
        'operator_update_id',
        'value',
        'datePaid',
        'payment_due_date',
        'is_active',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'datePaid' => 'datetime',
        'payment_due_date' => 'date',
        'value' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELACJE
    |--------------------------------------------------------------------------
    */

    public function shootingRange()
    {
        return $this->belongsTo(\App\Models\ShootingRange::class);
    }

    public function operatorInsert()
    {
        return $this->belongsTo(\App\Models\User::class, 'operator_insert_id');
    }

    public function operatorUpdate()
    {
        return $this->belongsTo(\App\Models\User::class, 'operator_update_id');
    }

    /*
    |--------------------------------------------------------------------------
    | LOGIKA BIZNESOWA
    |--------------------------------------------------------------------------
    */

    // ✅ czy aktualny (dzisiejsza data w zakresie)
    public function isActive(): bool
    {
        $today = Carbon::today();

        return $today->between(
            Carbon::parse($this->start_date),
            Carbon::parse($this->end_date)
        );
    }

    // ✅ liczba dni (włącznie z końcem)
    public function getDaysCount(): int
    {
        return Carbon::parse($this->start_date)
            ->diffInDays(Carbon::parse($this->end_date)) + 1;
    }

    // ✅ liczenie wartości (np. 10 zł za dzień)
    public function calculateValue(float $ratePerDay = 10): float
    {
        return $this->getDaysCount() * $ratePerDay;
    }

    // ✅ blokada nakładających się zakresów dat
    public static function hasOverlap($start, $end, $shootingRangeId, $ignoreId = null): bool
    {
        return self::where('shooting_range_id', $shootingRangeId)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('start_date', [$start, $end])
                    ->orWhereBetween('end_date', [$start, $end])
                    ->orWhere(function ($q2) use ($start, $end) {
                        $q2->where('start_date', '<=', $start)
                            ->where('end_date', '>=', $end);
                    });
            })
            ->exists();
    }

    // ✅ raport miesięczny
    public static function monthlyReport($year, $month)
    {
        return self::whereYear('start_date', $year)
            ->whereMonth('start_date', $month)
            ->get()
            ->groupBy('shooting_range_id')
            ->map(function ($items) {
                return [
                    'days' => $items->sum(fn ($i) => $i->getDaysCount()),
                    'value' => $items->sum('value'),
                ];
            });
    }

    // ✅ raport roczny
    public static function yearlyReport($year)
    {
        return self::whereYear('start_date', $year)
            ->get()
            ->groupBy('shooting_range_id')
            ->map(function ($items) {
                return [
                    'days' => $items->sum(fn ($i) => $i->getDaysCount()),
                    'value' => $items->sum('value'),
                ];
            });
    }

    /*
    |--------------------------------------------------------------------------
    | AUTOMATY
    |--------------------------------------------------------------------------
    */

    protected static function booted()
    {
        static::creating(function ($model) {
            if (auth()->check()) {
                $model->operator_insert_id = auth()->id();
                $model->operator_update_id = auth()->id();
            }

            // $model->value = $model->calculateValue();
        });

        static::updating(function ($model) {
            if (auth()->check()) {
                $model->operator_update_id = auth()->id();
            }

            // $model->value = $model->calculateValue();
        });
    }

    // --------
    public function isExpired(): bool
    {
        return Carbon::parse($this->end_date)->lt(Carbon::today());
    }

    public function isExpiringSoon(int $days = 3): bool
    {
        $end = Carbon::parse($this->end_date);
        $now = Carbon::today();

        return $end->between($now, $now->copy()->addDays($days));
    }

    public function daysToEnd(): int
    {
        return Carbon::today()->diffInDays(Carbon::parse($this->end_date), false);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShootingRangeInvitation extends Model
{
    public const STATUS_NONE = 'none';
    public const STATUS_PENDING = 'pending';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_REVOKED = 'revoked';
    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'shooting_range_id',
        'first_name',
        'last_name',
        'email',
        'user_id',
        'token',
        'status',
        'expires_at',
        'accepted_at',
        'revoked_at',
        'invited_by',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function shootingRange()
    {
        return $this->belongsTo(ShootingRange::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function inviter()
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACCEPTED
            && ! $this->revoked_at
            && (! $this->expires_at || $this->expires_at->isFuture());
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_NONE => 'Brak',
            self::STATUS_PENDING => 'Oczekujący',
            self::STATUS_ACCEPTED => 'Zaakceptowany',
            self::STATUS_REVOKED => 'Odwołany',
            self::STATUS_EXPIRED => 'Wygasł',
            default => $this->status,
        };
    }
}

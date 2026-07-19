<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    public const APPROVAL_APPROVED = 'approved';

    public const APPROVAL_PENDING = 'pending';

    public const APPROVAL_REJECTED = 'rejected';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'telephone',
        'avatar',
        'role',
        'is_approved',
        'approval_status',
        'approval_reason',
        'approved_at',
        'approved_by_user_id',
    ];

    protected $appends = [
        'avatar_url', // ,
        // 'is_blocked' // opcjonalnie: status bana
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_approved' => 'boolean',
            'approved_at' => 'datetime',
        ];
    }

    public function shootingRanges()
    {
        return $this->belongsToMany(
            ShootingRange::class,
            'shooting_range_employee',
            'employee_id',
            'shooting_ranges_id'
        )->withPivot([
            'role',
            'is_approved',
            'receives_reservation_notifications',
        ])
            ->withTimestamps();
    }

    public function reservations()
    {
        return $this->hasMany(Reservation::class, 'customer_user_id');
    }

    public function regulationAcceptances()
    {
        return $this->hasMany(RegulationAcceptance::class);
    }

    public function ipAddresses()
    {
        return $this->hasMany(UserIpAddress::class);
    }

    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class);
    }

    public function securityEvents()
    {
        return $this->hasMany(UserSecurityEvent::class);
    }

    public function requiredRegulations()
    {
        return Regulation::query()
            ->with('currentVersion')
            ->currentlyRequiredFor($this)
            ->get()
            ->filter(fn (Regulation $regulation): bool => $regulation->requiresAcceptanceFrom($this))
            ->values();
    }

    public function hasRequiredRegulationsToAccept(): bool
    {
        return $this->requiredRegulations()->isNotEmpty();
    }

    public function createdReservations()
    {
        return $this->hasMany(Reservation::class, 'created_by_user_id');
    }

    public function managesShootingRange(ShootingRange $shootingRange): bool
    {
        if (! $this->is_approved) {
            return false;
        }

        if ($this->isSuperAdmin()) {
            return true;
        }

        if (! $this->isAdmin() && ! $this->isEmployee()) {
            return false;
        }

        return $this->shootingRanges()
            ->whereKey($shootingRange->id)
            ->wherePivot('is_approved', true)
            ->wherePivotIn('role', ['admin', 'pracownik'])
            ->exists();
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification);
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    /* =====================================================
     | URL avatara (fallback)
     ===================================================== */
    public function getAvatarUrlAttribute(): string
    {
        if ($this->avatar) {
            return asset('storage/avatars/'.$this->avatar);
        }

        return 'https://i.pravatar.cc/150?u='.$this->email;
    }

    /* =====================================================
     |  ROLE / UPRAWNIENIA
     ===================================================== */
    public function isSuperAdmin()
    {
        return $this->role === 'superadmin';
    }

    public function isAdmin()
    {
        return $this->role === 'admin';
    }

    public function isEmployee()
    {
        return $this->role === 'pracownik';
    }

    public function isClient()
    {
        return $this->role === 'klient';
    }

    /* =====================================================
     |  WIADOMOŚCI
     ===================================================== */

    public function messages()
    {
        return $this->hasMany(Message::class, 'recipient_id');
    }

    public function unreadMessages()
    {
        return $this->messages()->whereNull('read_at');
    }

    public function unreadMessagesCount(): int
    {
        return $this->unreadMessages()->count();
    }

    /* =====================================================
     |  Lista banów wszystkich
     ===================================================== */
    public function bans()
    {
        return $this->hasMany(UserBan::class);
    }

    /* =====================================================
     |  Lista banów aktywnych
     ===================================================== */
    public function activeBans()
    {
        return $this->hasMany(UserBan::class)
            ->where('active', true);
    }
}

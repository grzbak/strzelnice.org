<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\UserBan;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

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
        'is_approved'
    ];

    protected $appends = [
        'avatar_url' //,
        //'is_blocked' // opcjonalnie: status bana
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
        ];
    }

    public function shootingRanges()
    {
        return $this->belongsToMany(
            ShootingRange::class,
            'shooting_range_employee',
            'employee_id',
            'shooting_ranges_id'
        )->withPivot(['role', 'is_approved'])
            ->withTimestamps();
    }

    /* =====================================================
     | URL avatara (fallback)
     ===================================================== */
    public function getAvatarUrlAttribute(): string
    {
        if ($this->avatar) {
            return asset('storage/avatars/' . $this->avatar);
        }

        return 'https://i.pravatar.cc/150?u=' . $this->email;
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

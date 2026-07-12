<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Regulation extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_REVOKED = 'revoked';

    public const ROLE_ALL = 'all';

    public const TARGET_ROLES = [
        self::ROLE_ALL => 'Wszyscy',
        'superadmin' => 'Superadmin',
        'admin' => 'Administrator strzelnicy',
        'pracownik' => 'Pracownik strzelnicy',
        'klient' => 'Klient',
    ];

    protected $fillable = [
        'page_id',
        'name',
        'target_roles',
        'starts_at',
        'ends_at',
        'is_indefinite',
        'status',
        'revoked_at',
    ];

    protected $casts = [
        'target_roles' => 'array',
        'starts_at' => 'date',
        'ends_at' => 'date',
        'is_indefinite' => 'boolean',
        'revoked_at' => 'datetime',
    ];

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(RegulationVersion::class);
    }

    public function currentVersion(): HasOne
    {
        return $this->hasOne(RegulationVersion::class)->latestOfMany('version_number');
    }

    public function acceptances(): HasMany
    {
        return $this->hasMany(RegulationAcceptance::class);
    }

    public function scopeCurrentlyRequiredFor(Builder $query, User $user): Builder
    {
        $today = today();

        return $query
            ->where('status', self::STATUS_ACTIVE)
            ->whereDate('starts_at', '<=', $today)
            ->where(function (Builder $query) use ($today): void {
                $query
                    ->where('is_indefinite', true)
                    ->orWhereNull('ends_at')
                    ->orWhereDate('ends_at', '>=', $today);
            })
            ->where(function (Builder $query) use ($user): void {
                $query
                    ->whereJsonContains('target_roles', self::ROLE_ALL)
                    ->orWhereJsonContains('target_roles', $user->role);
            })
            ->whereHas('currentVersion');
    }

    public function requiresAcceptanceFrom(User $user): bool
    {
        $version = $this->currentVersion;

        if (! $version) {
            return false;
        }

        return ! $user->regulationAcceptances()
            ->where('regulation_version_id', $version->id)
            ->exists();
    }

    public function targetRoleLabels(): string
    {
        return collect($this->target_roles)
            ->map(fn (string $role): string => self::TARGET_ROLES[$role] ?? $role)
            ->implode(', ');
    }
}

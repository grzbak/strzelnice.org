<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Model;
use App\Models\ShootingRangeOpeningHour;
use App\Models\ShootingRangeInterval;
use App\Models\ShootingRangeIntervalArchive;
use Illuminate\Support\Str;

class ShootingRange extends Model
{
    use HasFactory;

    protected $table = 'shooting_ranges';

    protected $fillable = [
        'name',
        'voivodeship',
        'town',
        'street',
        'houseNumber',
        'apartamentNumber',
        'plotNumber',
        'zipCode',
        'navLink',
        'lat',
        'lng',
        'email',
        'url',
        'facebook',
        'instagram',
        'telephone1',
        'telephone2',
        'type',
        'description',
        'brand_original_name',
        'brand_file_name',
        'is_visible',
        'is_public_access',
        'is_archived',
        'endpoint',
    ];

    protected $casts = [
        'lat' => 'float',
        'lng' => 'float',
        'is_visible' => 'boolean',
        'is_public_access' => 'boolean',
        'is_archived' => 'boolean',
    ];

    public function lanes(): HasMany
    {
        return $this->hasMany(Lane::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }
    /*
     |--------------------------------------------------------------------------
     | Accessory pomocnicze (opcjonalne)
     |--------------------------------------------------------------------------
     */

    public function getFullAddressAttribute(): string
    {
        return trim(
            "{$this->street} {$this->houseNumber}" .
                ($this->apartamentNumber ? "/{$this->apartamentNumber}" : '') .
                ", {$this->zipCode} {$this->town}"
        );
    }

    public function getBrandUrlAttribute(): ?string
    {
        return $this->brand_file_name
            ? asset('files/brands/' . $this->brand_file_name)
            : null;
    }

    public function isOpen(): bool
    {
        return $this->type === 'otwarta';
    }

    public function employees()
    {
        return $this->belongsToMany(
            User::class,
            'shooting_range_employee',
            'shooting_ranges_id',
            'employee_id'
        )->withPivot(['role', 'is_approved'])
            ->withTimestamps();
    }

    public function users()
    {
        return $this->belongsToMany(User::class)
            ->withPivot(['is_approved']);
    }

    public function openingHours()
    {
        return $this->hasMany(ShootingRangeOpeningHour::class);
    }

    public function holidays()
    {
        return $this->hasMany(ShootingRangeHoliday::class);
    }

    public function intervals()
    {
        return $this->hasMany(ShootingRangeInterval::class);
    }

    public function activeInterval()
    {
        return $this->hasOne(ShootingRangeInterval::class)
            ->whereDate('start_date', '<=', now())
            ->whereDate('end_date', '>=', now());
    }

    public function intervalArchives()
    {
        return $this->hasMany(ShootingRangeIntervalArchive::class);
    }

    public function packages()
    {
        return $this->hasMany(ShootingRangePackage::class);
    }

    public function invitations()
    {
        return $this->hasMany(ShootingRangeInvitation::class);
    }

    protected static function booted(): void
    {
        static::saving(function (ShootingRange $shootingRange) {
            if (! $shootingRange->exists || $shootingRange->isDirty('name')) {
                $shootingRange->endpoint = static::generateUniqueEndpoint(
                    $shootingRange->name,
                    $shootingRange->town,
                    $shootingRange->id
                );
            }
        });
    }

    public static function generateUniqueEndpoint(string $name, ?string $town = null, ?int $ignoreId = null): string
    {
        $base = static::normalizeEndpoint($name);

        if ($base === '') {
            $base = 'strzelnica';
        }

        $endpoint = $base;

        if (! static::endpointExists($endpoint, $ignoreId)) {
            return $endpoint;
        }

        if ($town) {
            $endpoint = $base . '_' . static::normalizeEndpoint($town);

            if (! static::endpointExists($endpoint, $ignoreId)) {
                return $endpoint;
            }
        }

        $counter = 2;

        do {
            $endpoint = $base . '_' . $counter;
            $counter++;
        } while (static::endpointExists($endpoint, $ignoreId));

        return $endpoint;
    }

    private static function normalizeEndpoint(string $value): string
    {
        return Str::of($value)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9\s_]+/', '')
            ->replaceMatches('/[\s_]+/', '_')
            ->trim('_')
            ->toString();
    }

    private static function endpointExists(string $endpoint, ?int $ignoreId = null): bool
    {
        $shootingRangeExists = static::query()
            ->where('endpoint', $endpoint)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists();

        if ($shootingRangeExists) {
            return true;
        }

        return Page::query()
            ->where('navigation_name', $endpoint)
            ->exists();
    }

    public function getPublicLinkAttribute(): string
    {
        return url('/s/' . $this->endpoint);
    }

    public function gallery(): HasOne
    {
        return $this->hasOne(ShootingRangeGalleryCategory::class);
    }
}

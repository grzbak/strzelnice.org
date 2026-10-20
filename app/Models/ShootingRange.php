<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
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
        'max_advance_booking_days',
        'requires_reservation_confirmation',
        'requires_shooting_leader',
        'is_archived',
        'endpoint',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'og_title',
        'og_description',
        'og_image',
        'robots',
    ];

    protected $casts = [
        'lat' => 'float',
        'lng' => 'float',
        'is_visible' => 'boolean',
        'is_public_access' => 'boolean',
        'max_advance_booking_days' => 'integer',
        'requires_reservation_confirmation' => 'boolean',
        'requires_shooting_leader' => 'boolean',
        'is_archived' => 'boolean',
    ];

    public function lanes(): HasMany
    {
        return $this->hasMany(Lane::class)
            ->where('is_archived', false);
    }

    public function allLanes(): HasMany
    {
        return $this->hasMany(Lane::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function paymentAccount(): HasOne
    {
        return $this->hasOne(ShootingRangePaymentAccount::class);
    }

    public function reservationPriceRules(): HasMany
    {
        return $this->hasMany(ReservationPriceRule::class);
    }

    public function reservationPricePromotions(): HasMany
    {
        return $this->hasMany(ReservationPricePromotion::class);
    }
    /*
     |--------------------------------------------------------------------------
     | Accessory pomocnicze (opcjonalne)
     |--------------------------------------------------------------------------
     */

    public function getFullAddressAttribute(): string
    {
        $street = trim(
            ($this->street ?? '').
            ($this->houseNumber ? ' '.$this->houseNumber : '').
            ($this->apartamentNumber ? '/'.$this->apartamentNumber : '')
        );

        $city = trim(
            ($this->zipCode ?? '').
            ($this->town ? ' '.$this->town : '')
        );

        $parts = array_values(array_filter([$street, $city]));

        if (count($parts) < 2) {
            return $parts[0] ?? '';
        }

        return $this->zipCode
            ? implode(', ', $parts)
            : implode(' ', $parts);
    }

    public function getBrandUrlAttribute(): ?string
    {
        return $this->brand_file_name
            ? asset('files/brands/'.$this->brand_file_name)
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
        )->withPivot([
            'role',
            'is_approved',
            'receives_reservation_notifications',
        ])
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
            ->where('is_active', true)
            ->whereDate('start_date', '<=', now())
            ->whereDate('end_date', '>=', now())
            ->orderByDesc('end_date');
    }

    public function intervalArchives()
    {
        return $this->hasMany(ShootingRangeIntervalArchive::class);
    }

    public function intervalEvents()
    {
        return $this->hasMany(ShootingRangeIntervalEvent::class);
    }

    public function packages()
    {
        return $this->hasMany(ShootingRangePackage::class);
    }

    public function packageOrders()
    {
        return $this->hasMany(PackageOrder::class);
    }

    public function billingCharges()
    {
        return $this->hasMany(BillingCharge::class);
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
            $endpoint = $base.static::normalizeEndpoint($town);

            if (! static::endpointExists($endpoint, $ignoreId)) {
                return $endpoint;
            }
        }

        $counter = 2;

        do {
            $endpoint = $base.$counter;
            $counter++;
        } while (static::endpointExists($endpoint, $ignoreId));

        return $endpoint;
    }

    private static function normalizeEndpoint(string $value): string
    {
        return Str::of($value)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9\s-]+/', '')
            ->replaceMatches('/[\s-]+/', '')
            ->trim()
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
        return url('/'.$this->endpoint);
    }

    /**
     * Czy strzelnica ma uzupełnione godziny otwarcia. Domyślne 00:00–23:59
     * dla wszystkich dni (dodawane automatycznie) traktujemy jako brak danych.
     */
    public function hasRealOpeningHours(): bool
    {
        $hours = $this->openingHours;

        if ($hours->isEmpty()) {
            return false;
        }

        return $hours->contains(function ($hour) {
            return $hour->opens_at?->format('H:i') !== '00:00'
                || ! in_array($hour->closes_at?->format('H:i'), ['23:59', '00:00'], true);
        }) || $hours->count() < 7;
    }

    /**
     * Strona województwa, do której należy strzelnica (null, gdy brak dopasowania).
     */
    public function getVoivodeshipLinkAttribute(): ?string
    {
        $slug = \App\Support\Voivodeships::slugFor($this->voivodeship);

        return $slug ? route('shootings.voivodeship', ['voivodeship' => $slug]) : null;
    }

    public function seoData(): array
    {
        $title = $this->meta_title ?: $this->defaultSeoTitle();
        $description = $this->meta_description ?: $this->defaultSeoDescription();

        return [
            'title' => $title,
            'description' => $description,
            'keywords' => $this->meta_keywords ?: $this->defaultSeoKeywords(),
            'og_title' => $this->og_title ?: $title,
            'og_description' => $this->og_description ?: $description,
            'og_image' => $this->og_image ?: $this->brand_url,
            'canonical_url' => $this->public_link,
            'robots' => $this->robots ?: 'index,follow',
        ];
    }

    private function defaultSeoTitle(): string
    {
        $name = trim((string) $this->name);
        $town = trim((string) $this->town);

        // "Strzelnica Texas – strzelnica Rozwadów, woj. lubelskie"
        $title = $name;

        if ($town !== '' && ! Str::contains(mb_strtolower($name), mb_strtolower($town))) {
            $title .= ' – strzelnica '.$town;
        } elseif ($town !== '') {
            $title .= ' – strzelnica';
        }

        if ($this->voivodeship) {
            $title .= ', woj. '.mb_strtolower($this->voivodeship);
        }

        return $title;
    }

    private function defaultSeoDescription(): string
    {
        $lanes = $this->relationLoaded('lanes')
            ? $this->lanes
                ->pluck('length')
                ->filter()
                ->map(fn ($length) => (int) $length)
                ->sortDesc()
                ->unique()
                ->map(fn ($length) => $length.' m')
                ->implode(', ')
            : '';

        return trim(sprintf(
            '%s%s%s. %sAdres, telefon, osie strzeleckie i rezerwacja stanowiska online.',
            $this->name,
            $this->full_address ? ', '.$this->full_address : '',
            $this->voivodeship ? ' (woj. '.mb_strtolower($this->voivodeship).')' : '',
            $lanes !== '' ? 'Osie: '.$lanes.'. ' : ''
        ));
    }

    private function defaultSeoKeywords(): string
    {
        return collect([
            'strzelnica',
            $this->name,
            $this->town ? 'strzelnica '.$this->town : null,
            $this->voivodeship,
            'rezerwacja strzelnicy',
        ])->filter()->implode(', ');
    }

    public function effectiveMaxAdvanceBookingDays(): int
    {
        if ($this->max_advance_booking_days !== null) {
            return max(0, (int) $this->max_advance_booking_days);
        }

        return max(
            0,
            (int) (SystemConfiguration::query()
                ->active()
                ->latest('id')
                ->value('default_max_advance_booking_days') ?? 30)
        );
    }

    public function latestBookableDate(): Carbon
    {
        return today()->addDays($this->effectiveMaxAdvanceBookingDays());
    }

    public function allowsReservationDate(Carbon $date): bool
    {
        $date = $date->copy()->startOfDay();

        return $date->greaterThanOrEqualTo(today())
            && $date->lessThanOrEqualTo($this->latestBookableDate());
    }

    public function gallery(): HasOne
    {
        return $this->hasOne(ShootingRangeGalleryCategory::class);
    }
}

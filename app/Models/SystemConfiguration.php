<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemConfiguration extends Model
{
    use HasFactory;

    public const DATA_FIELDS = [
        'company_name_1',
        'company_name_2',
        'company_name_3',
        'street',
        'building_number',
        'unit_number',
        'postal_code',
        'city',
        'voivodeship',
        'phone_1',
        'phone_2',
        'fax',
        'email',
        'bank_name',
        'bank_account_number',
        'default_max_advance_booking_days',
    ];

    protected $fillable = [
        ...self::DATA_FIELDS,
        'is_archived',
    ];

    protected $casts = [
        'is_archived' => 'boolean',
        'default_max_advance_booking_days' => 'integer',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_archived', false);
    }

    public static function normalizeBankAccountNumber(?string $value): ?string
    {
        $normalized = mb_strtoupper(trim((string) $value));
        $normalized = preg_replace('/[\s-]+/u', '', $normalized);

        if (str_starts_with($normalized, 'PL')) {
            $normalized = substr($normalized, 2);
        }

        return $normalized !== '' ? $normalized : null;
    }

    public static function formatBankAccountNumber(?string $value): ?string
    {
        $normalized = self::normalizeBankAccountNumber($value);

        if (! $normalized || ! ctype_digit($normalized)) {
            return $value;
        }

        $prefix = substr($normalized, 0, 2);
        $groups = str_split(substr($normalized, 2), 4);

        return trim($prefix.' '.implode(' ', $groups));
    }

    public function getFormattedBankAccountNumberAttribute(): ?string
    {
        return self::formatBankAccountNumber($this->bank_account_number);
    }
}

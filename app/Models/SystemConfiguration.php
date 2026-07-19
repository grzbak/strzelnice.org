<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemConfiguration extends Model
{
    use HasFactory;

    public const DEFAULT_RESERVATION_OPERATIONAL_MONTHS = 6;

    public const DEFAULT_RESERVATION_HISTORY_MONTHS = 60;

    public const DEFAULT_IP_RETENTION_MONTHS = 24;

    public const DEFAULT_AUDIT_RETENTION_MONTHS = 24;

    public const DEFAULT_SECURITY_EVENTS_RETENTION_MONTHS = 60;

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
        'reservation_operational_months',
        'reservation_history_months',
        'ip_retention_months',
        'audit_retention_months',
        'security_events_retention_months',
    ];

    protected $fillable = [
        ...self::DATA_FIELDS,
        'is_archived',
    ];

    protected $casts = [
        'is_archived' => 'boolean',
        'default_max_advance_booking_days' => 'integer',
        'reservation_operational_months' => 'integer',
        'reservation_history_months' => 'integer',
        'ip_retention_months' => 'integer',
        'audit_retention_months' => 'integer',
        'security_events_retention_months' => 'integer',
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

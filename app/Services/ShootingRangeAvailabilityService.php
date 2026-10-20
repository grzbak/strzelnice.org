<?php

namespace App\Services;

use App\Models\Lane;
use App\Models\Reservation;
use App\Models\ShootingRange;
use App\Models\ShootingRangeInvitation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ShootingRangeAvailabilityService
{
    public function basicRequirements(
        ShootingRange $shootingRange,
        ?Carbon $date = null
    ): array {
        if (! $shootingRange->is_visible) {
            return $this->denied(
                'Strzelnica jest ukryta. Dodawanie i edycja rezerwacji są zablokowane.'
            );
        }

        if ($shootingRange->is_archived) {
            return $this->denied('Strzelnica jest zarchiwizowana.');
        }

        if (! $this->hasActiveAdministrator($shootingRange)) {
            return $this->denied('Strzelnica nie posiada aktywnego administratora.');
        }

        if (! $shootingRange->openingHours()->exists()) {
            return $this->denied('Strzelnica nie posiada zdefiniowanych godzin pracy.');
        }

        if (! $shootingRange->lanes()->exists()) {
            return $this->denied('Strzelnica nie posiada osi strzeleckiej.');
        }

        if ($date && ! $this->hasCurrentBookableInterval($shootingRange)) {
            return $this->denied(
                'Strzelnica nie posiada obecnie aktywnego przedziału lub upłynął termin płatności.'
            );
        }

        return $this->allowed();
    }

    public function availableForSearch(
        ShootingRange $shootingRange,
        Carbon $date,
        int $minimumLaneLength = 0,
        ?User $user = null,
        bool $requireUserAccess = true
    ): array {
        $basicResult = $this->basicRequirements($shootingRange, $date);

        if (! $basicResult['allowed']) {
            return $basicResult;
        }

        if (! $shootingRange->allowsReservationDate($date)) {
            return $this->denied('Wybrana data znajduje się poza dozwolonym terminem rezerwacji.');
        }

        if (
            $requireUserAccess
            && ! $this->canUserAccessRange($shootingRange, $user, $date)
        ) {
            return $this->denied('Strzelnica wymaga aktywnego zaproszenia.');
        }

        if ($this->isHoliday($shootingRange, $date)) {
            return $this->denied('Wybrany dzień jest dniem wolnym.');
        }

        $openingHours = $this->openingHoursForDate($shootingRange, $date);

        if ($openingHours->isEmpty()) {
            return $this->denied('Strzelnica jest nieczynna w wybranym dniu tygodnia.');
        }

        $lanes = $shootingRange->relationLoaded('lanes')
            ? $shootingRange->lanes
            : $shootingRange->lanes()->get();
        $lanes = $lanes->when(
            $minimumLaneLength > 0,
            fn (Collection $items) => $items->where(
                'length',
                '>=',
                $minimumLaneLength
            )
        );
        $hasFreeSlot = $lanes->contains(
            function (Lane $lane) use ($shootingRange, $date) {
                $available = $this->maximumAvailablePositionsForDate(
                    $lane,
                    $shootingRange,
                    $date
                );
                $lane->search_available_positions = $available;

                return $available > 0;
            }
        );

        return $hasFreeSlot
            ? $this->allowed()
            : $this->denied('Brak wolnych stanowisk na osiach spełniających kryteria.');
    }

    public function validateReservation(
        ShootingRange $shootingRange,
        Lane $lane,
        User $actor,
        Carbon $start,
        Carbon $end,
        int $positionsCount,
        bool $createdByStaff = false,
        bool $isDynamicShooting = false,
        ?int $ignoreReservationId = null
    ): void {
        if ((int) $lane->shooting_range_id !== (int) $shootingRange->id) {
            $this->throwError('Wybrana oś nie należy do tej strzelnicy.');
        }

        if ($createdByStaff && ! $actor->managesShootingRange($shootingRange)) {
            $this->throwError('Nie masz uprawnień do zarządzania rezerwacjami tej strzelnicy.');
        }

        if ($isDynamicShooting && ! $lane->allows_dynamic_shooting) {
            $this->throwError(
                'Wybrana oś nie jest przystosowana do strzelania dynamicznego.'
            );
        }

        if (
            $isDynamicShooting
            && $positionsCount !== (int) $lane->numberOfPositions
        ) {
            $this->throwError(
                'Strzelanie dynamiczne wymaga rezerwacji wszystkich stanowisk osi.'
            );
        }

        if ($positionsCount < 1 || $positionsCount > (int) $lane->numberOfPositions) {
            $this->throwError('Wybrana liczba stanowisk przekracza pojemność osi.');
        }

        if (
            $end->lessThanOrEqualTo($start)
            || ! $this->endsSameDayOrAtMidnight($start, $end)
        ) {
            $this->throwError('Rezerwacja musi kończyć się później i tego samego dnia.');
        }

        if (! $this->usesThirtyMinuteSlots($start, $end)) {
            $this->throwError('Początek, koniec i długość rezerwacji muszą odpowiadać przedziałom 30-minutowym.');
        }

        if ($start->lessThan(now())) {
            $this->throwError('Nie można rezerwować terminu, który już minął.');
        }

        if (! $shootingRange->allowsReservationDate($start)) {
            $days = $shootingRange->effectiveMaxAdvanceBookingDays();
            $this->throwError(
                "Rezerwacji można dokonać maksymalnie {$days} dni do przodu."
            );
        }

        $basicResult = $this->basicRequirements($shootingRange, $start);

        if (! $basicResult['allowed']) {
            $this->throwError($basicResult['reason']);
        }

        if (! $createdByStaff && ! $this->canUserAccessRange($shootingRange, $actor, $start)) {
            $this->throwError('Ta strzelnica wymaga aktywnego i zaakceptowanego zaproszenia.');
        }

        if ($this->isHoliday($shootingRange, $start)) {
            $this->throwError('Wybrany dzień jest dniem wolnym.');
        }

        if (! $this->reservationFitsOpeningHours($shootingRange, $start, $end)) {
            $this->throwError('Rezerwacja nie mieści się w godzinach pracy strzelnicy.');
        }

        if (
            $this->availablePositions(
                $lane,
                $start,
                $end,
                $ignoreReservationId
            ) < $positionsCount
        ) {
            $this->throwError('W wybranym czasie nie ma wystarczającej liczby wolnych stanowisk.');
        }
    }

    public function canUserAccessRange(
        ShootingRange $shootingRange,
        ?User $user,
        Carbon $date
    ): bool {
        if ($shootingRange->is_public_access) {
            return true;
        }

        if (! $user) {
            return false;
        }

        if (! $user->is_approved) {
            return false;
        }

        if ($user->managesShootingRange($shootingRange)) {
            return true;
        }

        return ShootingRangeInvitation::query()
            ->where('shooting_range_id', $shootingRange->id)
            ->where('status', ShootingRangeInvitation::STATUS_ACCEPTED)
            ->whereNull('revoked_at')
            ->where(function ($query) use ($user) {
                $query
                    ->where('user_id', $user->id)
                    ->orWhereRaw('LOWER(email) = ?', [mb_strtolower($user->email)]);
            })
            ->where(function ($query) use ($date) {
                $query
                    ->whereNull('expires_at')
                    ->orWhere('expires_at', '>=', $date->copy()->endOfDay());
            })
            ->exists();
    }

    public function availablePositions(
        Lane $lane,
        Carbon $start,
        Carbon $end,
        ?int $ignoreReservationId = null
    ): int {
        $minimumAvailable = (int) $lane->numberOfPositions;
        $slotStart = $start->copy();

        while ($slotStart->lt($end)) {
            $slotEnd = $slotStart->copy()->addMinutes(30)->min($end);
            $reserved = (int) Reservation::query()
                ->where('line_id', $lane->id)
                ->whereIn('status', [
                    Reservation::STATUS_PENDING,
                    Reservation::STATUS_APPROVED,
                ])
                ->where(function ($query) {
                    $query->where('payment_status', '!=', Reservation::PAYMENT_EXPIRED)
                        ->orWhereNull('payment_status');
                })
                ->when(
                    $ignoreReservationId,
                    fn ($query) => $query->where('id', '!=', $ignoreReservationId)
                )
                ->where('start', '<', $slotEnd)
                ->where('end', '>', $slotStart)
                ->sum('positions_count');

            $minimumAvailable = min(
                $minimumAvailable,
                max(0, (int) $lane->numberOfPositions - $reserved)
            );
            $slotStart->addMinutes(30);
        }

        return $minimumAvailable;
    }

    public function maximumAvailablePositionsForDate(
        Lane $lane,
        ShootingRange $shootingRange,
        Carbon $date
    ): int {
        $reservations = Reservation::query()
            ->where('line_id', $lane->id)
            ->whereIn('status', [
                Reservation::STATUS_PENDING,
                Reservation::STATUS_APPROVED,
            ])
            ->where(function ($query) {
                $query->where('payment_status', '!=', Reservation::PAYMENT_EXPIRED)
                    ->orWhereNull('payment_status');
            })
            ->whereDate('start', $date)
            ->get(['start', 'end', 'positions_count']);

        return $this->openingHoursForDate($shootingRange, $date)
            ->map(function ($openingHour) use ($lane, $date, $reservations) {
                $slotStart = $this->combineDateAndTime($date, $openingHour->opens_at);
                $closing = $this->combineClosingDateAndTime($date, $openingHour->closes_at);
                $maximum = 0;

                while ($slotStart->copy()->addMinutes(30)->lessThanOrEqualTo($closing)) {
                    $slotEnd = $slotStart->copy()->addMinutes(30);
                    $reserved = (int) $reservations
                        ->filter(
                            fn (Reservation $reservation) => $reservation->start->lt($slotEnd)
                                && $reservation->end->gt($slotStart)
                        )
                        ->sum('positions_count');
                    $maximum = max(
                        $maximum,
                        max(
                            0,
                            (int) $lane->numberOfPositions - $reserved
                        )
                    );
                    $slotStart->addMinutes(30);
                }

                return $maximum;
            })
            ->max() ?? 0;
    }

    public function hasBookableIntervalForDate(
        ShootingRange $shootingRange,
        Carbon $date
    ): bool {
        return $shootingRange->intervals()
            ->where('is_active', true)
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->where(function ($query) use ($date) {
                $query
                    ->whereNotNull('datePaid')
                    ->orWhere(function ($query) use ($date) {
                        $query
                            ->whereNull('datePaid')
                            ->whereDate('payment_due_date', '>=', $date);
                    });
            })
            ->exists();
    }

    public function hasCurrentBookableInterval(
        ShootingRange $shootingRange
    ): bool {
        return $this->hasBookableIntervalForDate($shootingRange, today());
    }

    private function hasActiveAdministrator(ShootingRange $shootingRange): bool
    {
        return $shootingRange->employees()
            ->where('shooting_range_employee.role', 'admin')
            ->where('shooting_range_employee.is_approved', true)
            ->where('users.is_approved', true)
            ->exists();
    }

    private function isHoliday(ShootingRange $shootingRange, Carbon $date): bool
    {
        return $shootingRange->holidays()
            ->whereDate('date', $date)
            ->exists();
    }

    private function openingHoursForDate(
        ShootingRange $shootingRange,
        Carbon $date
    ): Collection {
        if ($shootingRange->relationLoaded('openingHours')) {
            return $shootingRange->openingHours
                ->where('day_of_week', $date->isoWeekday())
                ->values();
        }

        return $shootingRange->openingHours()
            ->where('day_of_week', $date->isoWeekday())
            ->get();
    }

    private function reservationFitsOpeningHours(
        ShootingRange $shootingRange,
        Carbon $start,
        Carbon $end
    ): bool {
        return $this->openingHoursForDate($shootingRange, $start)
            ->contains(function ($openingHour) use ($start, $end) {
                $opensAt = $this->combineDateAndTime($start, $openingHour->opens_at);
                $closesAt = $this->combineClosingDateAndTime($start, $openingHour->closes_at);

                return $start->greaterThanOrEqualTo($opensAt)
                    && $end->lessThanOrEqualTo($closesAt);
            });
    }

    private function usesThirtyMinuteSlots(Carbon $start, Carbon $end): bool
    {
        return in_array($start->minute, [0, 30], true)
            && in_array($end->minute, [0, 30], true)
            && $start->second === 0
            && $end->second === 0
            && ((int) $start->diffInMinutes($end)) % 30 === 0;
    }

    private function endsSameDayOrAtMidnight(Carbon $start, Carbon $end): bool
    {
        return $start->isSameDay($end)
            || (
                $end->isSameDay($start->copy()->addDay())
                && $end->isStartOfDay()
            );
    }

    private function combineDateAndTime(Carbon $date, mixed $time): Carbon
    {
        return Carbon::parse(
            $date->format('Y-m-d').' '.Carbon::parse($time)->format('H:i:s')
        );
    }

    private function combineClosingDateAndTime(Carbon $date, mixed $time): Carbon
    {
        $closing = $this->combineDateAndTime($date, $time);

        return $closing->format('H:i') === '23:59'
            ? $date->copy()->addDay()->startOfDay()
            : $closing;
    }

    private function allowed(): array
    {
        return ['allowed' => true, 'reason' => null];
    }

    private function denied(string $reason): array
    {
        return ['allowed' => false, 'reason' => $reason];
    }

    private function throwError(string $message): never
    {
        throw ValidationException::withMessages([
            'reservation' => $message,
        ]);
    }
}

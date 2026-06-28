<?php

namespace App\Services;

use App\Models\Lane;
use App\Models\Reservation;
use App\Models\ShootingRange;
use App\Models\ShootingRangeInvitation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class ShootingRangeAvailabilityService
{
    /**
     * Sprawdza podstawowe warunki umożliwiające rezerwacje.
     */
    public function basicRequirements(ShootingRange $shootingRange): array
    {
        if (! $shootingRange->is_visible) {
            return $this->denied('Strzelnica nie jest widoczna.');
        }

        $hasActiveAdmin = $shootingRange->employees()
            ->where('shooting_range_employee.role', 'admin')
            ->where('shooting_range_employee.is_approved', true)
            ->where('users.is_approved', true)
            ->exists();

        if (! $hasActiveAdmin) {
            return $this->denied(
                'Strzelnica nie posiada aktywnego administratora.'
            );
        }

        $hasActiveInterval = $shootingRange->intervals()
            ->where('is_active', true)
            ->whereDate('start_date', '<=', today())
            ->whereDate('end_date', '>=', today())
            ->exists();

        if (! $hasActiveInterval) {
            return $this->denied(
                'Strzelnica nie posiada aktywnego przedziału.'
            );
        }

        if (! $shootingRange->openingHours()->exists()) {
            return $this->denied(
                'Strzelnica nie posiada zdefiniowanych godzin pracy.'
            );
        }

        if (! $shootingRange->lanes()->exists()) {
            return $this->denied(
                'Strzelnica nie posiada osi strzeleckiej.'
            );
        }

        return $this->allowed();
    }

    /**
     * Sprawdza dostępność strzelnicy dla wyszukiwarki.
     */
    public function availableForSearch(
        ShootingRange $shootingRange,
        Carbon $date,
        int $minimumLaneLength = 0
    ): array {
        $basicResult = $this->basicRequirements($shootingRange);

        if (! $basicResult['allowed']) {
            return $basicResult;
        }

        if (! $shootingRange->allowsReservationDate($date)) {
            return $this->denied(
                'Wybrana data znajduje się poza dozwolonym terminem rezerwacji.'
            );
        }

        if (! $this->hasActiveIntervalForDate($shootingRange, $date)) {
            return $this->denied(
                'Strzelnica nie posiada aktywnego przedziału dla wybranej daty.'
            );
        }

        if ($this->isHoliday($shootingRange, $date)) {
            return $this->denied(
                'Wybrany dzień jest dniem wolnym.'
            );
        }

        $openingHours = $this->openingHoursForDate($shootingRange, $date);

        if ($openingHours->isEmpty()) {
            return $this->denied(
                'Strzelnica jest nieczynna w wybranym dniu tygodnia.'
            );
        }

        $lanes = $shootingRange->lanes()
            ->when(
                $minimumLaneLength > 0,
                fn ($query) => $query->where(
                    'length',
                    '>=',
                    $minimumLaneLength
                )
            )
            ->get();

        foreach ($lanes as $lane) {
            foreach ($openingHours as $openingHour) {
                if ($this->laneHasAnyFreeSlot(
                    $lane,
                    $date,
                    $openingHour->opens_at,
                    $openingHour->closes_at
                )) {
                    return $this->allowed();
                }
            }
        }

        return $this->denied(
            'Brak wolnych stanowisk na osiach spełniających kryteria.'
        );
    }

    /**
     * Walidacja wykonywana bezpośrednio przed zapisem rezerwacji.
     *
     * Metodę należy wywołać wewnątrz DB::transaction().
     */
    public function validateReservation(
        ShootingRange $shootingRange,
        Lane $lane,
        User $user,
        Carbon $start,
        Carbon $end,
        int $positionsCount
    ): void {
        $basicResult = $this->basicRequirements($shootingRange);

        if (! $basicResult['allowed']) {
            $this->throwError($basicResult['reason']);
        }

        if ((int) $lane->shooting_range_id !== (int) $shootingRange->id) {
            $this->throwError('Wybrana oś nie należy do tej strzelnicy.');
        }

        if ($positionsCount < 1) {
            $this->throwError('Liczba stanowisk musi wynosić przynajmniej 1.');
        }

        if ($positionsCount > $lane->numberOfPositions) {
            $this->throwError(
                'Wybrana liczba stanowisk przekracza pojemność osi.'
            );
        }

        if ($end->lessThanOrEqualTo($start)) {
            $this->throwError(
                'Godzina zakończenia musi być późniejsza od rozpoczęcia.'
            );
        }

        if (! $start->isSameDay($end)) {
            $this->throwError(
                'Rezerwacja musi rozpoczynać się i kończyć tego samego dnia.'
            );
        }

        if (! $shootingRange->allowsReservationDate($start)) {
            $this->throwError(
                'Wybrana data znajduje się poza dozwolonym terminem rezerwacji.'
            );
        }

        if (! $this->hasActiveIntervalForDate($shootingRange, $start)) {
            $this->throwError(
                'Strzelnica nie posiada aktywnego przedziału dla wybranej daty.'
            );
        }

        if ($this->isHoliday($shootingRange, $start)) {
            $this->throwError(
                'Wybrany dzień jest dniem wolnym.'
            );
        }

        if (! $this->reservationFitsOpeningHours(
            $shootingRange,
            $start,
            $end
        )) {
            $this->throwError(
                'Rezerwacja nie mieści się w godzinach pracy strzelnicy.'
            );
        }

        if (! $shootingRange->is_public_access) {
            $this->validatePrivateRangeAccess($shootingRange, $user);
        }

        $availablePositions = $this->availablePositions(
            $lane,
            $start,
            $end
        );

        if ($availablePositions < $positionsCount) {
            $this->throwError(
                'W wybranym czasie nie ma wystarczającej liczby wolnych stanowisk.'
            );
        }
    }

    /**
     * Liczba wolnych stanowisk w całym wskazanym przedziale.
     */
    public function availablePositions(
        Lane $lane,
        Carbon $start,
        Carbon $end
    ): int {
        $reservedPositions = Reservation::query()
            ->where('line_id', $lane->id)
            ->whereIn('status', ['pending', 'approved'])
            ->where('start', '<', $end)
            ->where('end', '>', $start)
            ->sum('positions_count');

        return max(
            0,
            (int) $lane->numberOfPositions - (int) $reservedPositions
        );
    }

    private function laneHasAnyFreeSlot(
        Lane $lane,
        Carbon $date,
        mixed $opensAt,
        mixed $closesAt
    ): bool {
        $start = $this->combineDateAndTime($date, $opensAt);
        $closing = $this->combineDateAndTime($date, $closesAt);

        while ($start->copy()->addMinutes(30)->lessThanOrEqualTo($closing)) {
            $end = $start->copy()->addMinutes(30);

            if ($this->availablePositions($lane, $start, $end) > 0) {
                return true;
            }

            $start->addMinutes(30);
        }

        return false;
    }

    private function hasActiveIntervalForDate(
        ShootingRange $shootingRange,
        Carbon $date
    ): bool {
        return $shootingRange->intervals()
            ->where('is_active', true)
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->exists();
    }

    private function isHoliday(
        ShootingRange $shootingRange,
        Carbon $date
    ): bool {
        return $shootingRange->holidays()
            ->whereDate('date', $date)
            ->exists();
    }

    private function openingHoursForDate(
        ShootingRange $shootingRange,
        Carbon $date
    ) {
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
                $opensAt = $this->combineDateAndTime(
                    $start,
                    $openingHour->opens_at
                );

                $closesAt = $this->combineDateAndTime(
                    $start,
                    $openingHour->closes_at
                );

                return $start->greaterThanOrEqualTo($opensAt)
                    && $end->lessThanOrEqualTo($closesAt);
            });
    }

    private function validatePrivateRangeAccess(
        ShootingRange $shootingRange,
        User $user
    ): void {
        $hasInvitation = ShootingRangeInvitation::query()
            ->where('shooting_range_id', $shootingRange->id)
            ->where('email', $user->email)
            ->where('status', 'accepted')
            ->where(function ($query) {
                $query
                    ->whereNull('expires_at')
                    ->orWhere('expires_at', '>=', now());
            })
            ->exists();

        if (! $hasInvitation) {
            $this->throwError(
                'Ta strzelnica wymaga aktywnego i zaakceptowanego zaproszenia.'
            );
        }
    }

    private function combineDateAndTime(
        Carbon $date,
        mixed $time
    ): Carbon {
        $formattedTime = Carbon::parse($time)->format('H:i:s');

        return Carbon::parse(
            $date->format('Y-m-d') . ' ' . $formattedTime
        );
    }

    private function allowed(): array
    {
        return [
            'allowed' => true,
            'reason' => null,
        ];
    }

    private function denied(string $reason): array
    {
        return [
            'allowed' => false,
            'reason' => $reason,
        ];
    }

    private function throwError(string $message): never
    {
        throw ValidationException::withMessages([
            'reservation' => $message,
        ]);
    }
}

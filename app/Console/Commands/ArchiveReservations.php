<?php

namespace App\Console\Commands;

use App\Models\Reservation;
use App\Models\ReservationArchive;
use App\Models\SystemConfiguration;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ArchiveReservations extends Command
{
    protected $signature = 'reservations:archive-and-purge
        {--dry-run : Wyświetla liczbę rekordów bez wprowadzania zmian}
        {--chunk=500 : Liczba rezerwacji przetwarzanych w jednej transakcji}';

    protected $description = 'Archiwizuje zakończone rezerwacje i usuwa historię po upływie okresu retencji.';

    public function handle(): int
    {
        $configuration = SystemConfiguration::query()
            ->active()
            ->latest('id')
            ->first();

        $operationalMonths = max(
            1,
            (int) (
                $configuration?->reservation_operational_months
                ?? SystemConfiguration::DEFAULT_RESERVATION_OPERATIONAL_MONTHS
            )
        );
        $historyMonths = max(
            $operationalMonths,
            (int) (
                $configuration?->reservation_history_months
                ?? SystemConfiguration::DEFAULT_RESERVATION_HISTORY_MONTHS
            )
        );
        $chunkSize = max(100, min(5000, (int) $this->option('chunk')));
        $archiveBefore = now()->subMonthsNoOverflow($operationalMonths);
        $deleteBefore = now()->subMonthsNoOverflow($historyMonths);

        $reservationsToArchive = Reservation::query()
            ->whereIn('status', [
                Reservation::STATUS_CANCELLED,
                Reservation::STATUS_COMPLETED,
                Reservation::STATUS_NO_SHOW,
            ])
            ->where('end', '<', $archiveBefore);

        $archivesToDelete = ReservationArchive::query()
            ->where('end', '<', $deleteBefore);

        if ($this->option('dry-run')) {
            $this->info(
                'Rezerwacje do archiwizacji: '
                .(clone $reservationsToArchive)->count()
            );
            $this->info(
                'Wpisy archiwalne do trwałego usunięcia: '
                .(clone $archivesToDelete)->count()
            );
            $this->line(
                "Okres operacyjny: {$operationalMonths} mies.; "
                ."łączna historia: {$historyMonths} mies."
            );

            return self::SUCCESS;
        }

        $archivedCount = 0;
        $purgedCount = 0;

        $reservationsToArchive
            ->orderBy('id')
            ->chunkById(
                $chunkSize,
                function (Collection $reservations) use (
                    &$archivedCount,
                    $archiveBefore
                ): void {
                    DB::transaction(function () use (
                        $reservations,
                        &$archivedCount,
                        $archiveBefore
                    ): void {
                        $lockedReservations = Reservation::query()
                            ->with([
                                'shootingRange:id,name,town',
                                'lane:id,name,length',
                            ])
                            ->whereKey($reservations->modelKeys())
                            ->whereIn('status', [
                                Reservation::STATUS_CANCELLED,
                                Reservation::STATUS_COMPLETED,
                                Reservation::STATUS_NO_SHOW,
                            ])
                            ->where('end', '<', $archiveBefore)
                            ->lockForUpdate()
                            ->get();

                        if ($lockedReservations->isEmpty()) {
                            return;
                        }

                        $archivedAt = now();
                        $rows = $lockedReservations
                            ->map(
                                fn (Reservation $reservation): array => $this
                                    ->archiveRow($reservation, $archivedAt)
                            )
                            ->all();

                        DB::table('reservation_archives')
                            ->insertOrIgnore($rows);

                        $archivedIds = ReservationArchive::query()
                            ->whereIn(
                                'original_reservation_id',
                                $lockedReservations->modelKeys()
                            )
                            ->pluck('original_reservation_id');

                        $archivedCount += Reservation::query()
                            ->whereKey($archivedIds)
                            ->get()
                            ->each(fn (Reservation $reservation) => $reservation->forceDelete())
                            ->count();
                    });
                }
            );

        $archivesToDelete
            ->orderBy('id')
            ->chunkById(
                $chunkSize,
                function (Collection $archives) use (&$purgedCount): void {
                    $purgedCount += ReservationArchive::query()
                        ->whereKey($archives->modelKeys())
                        ->delete();
                }
            );

        Log::info('Reservation archiving completed.', [
            'operational_months' => $operationalMonths,
            'history_months' => $historyMonths,
            'archived_reservations' => $archivedCount,
            'purged_archives' => $purgedCount,
        ]);

        $this->info("Zarchiwizowane rezerwacje: {$archivedCount}");
        $this->info("Trwale usunięte wpisy archiwalne: {$purgedCount}");

        return self::SUCCESS;
    }

    private function archiveRow(
        Reservation $reservation,
        Carbon $archivedAt
    ): array {
        return [
            'original_reservation_id' => $reservation->id,
            'user_id' => $reservation->user_id,
            'mod_id' => $reservation->mod_id,
            'customer_user_id' => $reservation->customer_user_id,
            'customer_name' => $reservation->customer_name,
            'customer_email' => $reservation->customer_email,
            'customer_phone' => $reservation->customer_phone,
            'created_by_user_id' => $reservation->created_by_user_id,
            'source' => $reservation->source,
            'shooting_range_id' => $reservation->shooting_range_id,
            'shooting_range_name' => $reservation->shootingRange?->name,
            'shooting_range_town' => $reservation->shootingRange?->town,
            'line_id' => $reservation->line_id,
            'lane_name' => $reservation->lane?->name,
            'lane_length' => $reservation->lane?->length,
            'start' => $reservation->start,
            'end' => $reservation->end,
            'positions_count' => $reservation->positions_count,
            'is_dynamic_shooting' => $reservation->is_dynamic_shooting,
            'has_shooting_leader_license' => $reservation->has_shooting_leader_license,
            'shooting_leader_license_number' => $reservation->shooting_leader_license_number,
            'status' => $reservation->status,
            'approved_by_user_id' => $reservation->approved_by_user_id,
            'approved_at' => $reservation->approved_at,
            'cancelled_by_user_id' => $reservation->cancelled_by_user_id,
            'cancelled_at' => $reservation->cancelled_at,
            'finalized_by_user_id' => $reservation->finalized_by_user_id,
            'finalized_at' => $reservation->finalized_at,
            'cancellation_reason' => $reservation->cancellation_reason,
            'notes' => $reservation->notes,
            'reservation_created_at' => $reservation->created_at,
            'reservation_updated_at' => $reservation->updated_at,
            'archived_at' => $archivedAt,
        ];
    }
}

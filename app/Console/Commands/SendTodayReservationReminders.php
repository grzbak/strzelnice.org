<?php

namespace App\Console\Commands;

use App\Mail\ReservationTodayReminderMail;
use App\Models\Reservation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendTodayReservationReminders extends Command
{
    protected $signature = 'reservations:send-today-reminders {--dry-run}';

    protected $description = 'Sends reminders for reservations scheduled for today and created more than 24 hours before start.';

    public function handle(): int
    {
        $todayStart = today()->startOfDay();
        $tomorrowStart = $todayStart->copy()->addDay();

        $reservations = Reservation::query()
            ->with(['shootingRange', 'lane', 'customer', 'user'])
            ->whereNull('today_reminder_sent_at')
            ->whereIn('status', [
                Reservation::STATUS_PENDING,
                Reservation::STATUS_APPROVED,
            ])
            ->where('start', '>=', $todayStart)
            ->where('start', '<', $tomorrowStart)
            ->orderBy('start')
            ->get()
            ->filter(function (Reservation $reservation): bool {
                return $reservation->created_at
                    && $reservation->created_at->lte(
                        $reservation->start->copy()->subDay()
                    )
                    && filter_var(
                        $this->recipientEmail($reservation),
                        FILTER_VALIDATE_EMAIL
                    );
            })
            ->values();

        if ($this->option('dry-run')) {
            $this->info("Reservation reminders to send: {$reservations->count()}");

            return self::SUCCESS;
        }

        foreach ($reservations as $reservation) {
            Mail::to($this->recipientEmail($reservation))
                ->send(new ReservationTodayReminderMail($reservation));

            $reservation->forceFill([
                'today_reminder_sent_at' => now(),
            ])->save();
        }

        $this->info("Reservation reminders sent: {$reservations->count()}");

        return self::SUCCESS;
    }

    private function recipientEmail(Reservation $reservation): ?string
    {
        return $reservation->customer_email
            ?? $reservation->customer?->email
            ?? $reservation->user?->email;
    }
}

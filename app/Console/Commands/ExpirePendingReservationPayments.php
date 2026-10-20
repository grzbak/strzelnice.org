<?php

namespace App\Console\Commands;

use App\Services\ReservationPaymentService;
use Illuminate\Console\Command;

class ExpirePendingReservationPayments extends Command
{
    protected $signature = 'reservation-payments:expire-pending';

    protected $description = 'Expires pending reservation payments and releases blocked reservation slots.';

    public function handle(ReservationPaymentService $reservationPayments): int
    {
        $expiredCount = $reservationPayments->expirePendingPayments();

        $this->info("Expired reservation payments: {$expiredCount}");

        return self::SUCCESS;
    }
}

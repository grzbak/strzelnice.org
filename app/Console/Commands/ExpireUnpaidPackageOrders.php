<?php

namespace App\Console\Commands;

use App\Models\PackageOrder;
use App\Services\ShootingRangeSubscriptionService;
use Illuminate\Console\Command;

class ExpireUnpaidPackageOrders extends Command
{
    protected $signature = 'packages:expire-unpaid-orders {--dry-run}';

    protected $description = 'Blocks unpaid package orders after the configured payment due date.';

    public function handle(ShootingRangeSubscriptionService $subscriptions): int
    {
        $withdrawnOrders = PackageOrder::query()
            ->with(['interval', 'payments', 'package', 'shootingRange'])
            ->where('status', PackageOrder::STATUS_CONDITIONAL_ACTIVE)
            ->where('payment_status', PackageOrder::PAYMENT_PENDING)
            ->where(function ($query) {
                $query->whereNull('payment_due_at')
                    ->orWhere('payment_due_at', '>=', now());
            })
            ->get()
            ->filter(fn (PackageOrder $order): bool => ! $subscriptions->isOrderPayable($order));

        $orders = PackageOrder::query()
            ->with(['interval', 'billingCharges'])
            ->where('status', PackageOrder::STATUS_CONDITIONAL_ACTIVE)
            ->where('payment_status', PackageOrder::PAYMENT_PENDING)
            ->whereNotNull('payment_due_at')
            ->where('payment_due_at', '<', now())
            ->get();

        if ($this->option('dry-run')) {
            $this->info("Orders to expire: {$orders->count()}");
            $this->info("Orders to cancel because package is unavailable: {$withdrawnOrders->count()}");

            return self::SUCCESS;
        }

        foreach ($withdrawnOrders as $order) {
            $subscriptions->cancelUnpaidOrder($order, note: 'Pakiet nie jest juz dostepny do oplacenia.');
        }

        foreach ($orders as $order) {
            $subscriptions->expireUnpaidOrder($order);
        }

        $this->info("Expired package orders: {$orders->count()}");
        $this->info("Cancelled unavailable package orders: {$withdrawnOrders->count()}");

        return self::SUCCESS;
    }
}

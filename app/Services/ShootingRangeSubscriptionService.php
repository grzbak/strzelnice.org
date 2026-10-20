<?php

namespace App\Services;

use App\Models\BillingCharge;
use App\Models\PackageOrder;
use App\Models\Payment;
use App\Models\ShootingRange;
use App\Models\ShootingRangeInterval;
use App\Models\ShootingRangeIntervalArchive;
use App\Models\ShootingRangeIntervalEvent;
use App\Models\ShootingRangePackage;
use App\Models\SystemConfiguration;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ShootingRangeSubscriptionService
{
    public function availablePackagesFor(ShootingRange $shootingRange, bool $includeSpecial = false)
    {
        $today = now()->toDateString();

        return ShootingRangePackage::query()
            ->with('shootingRange')
            ->where('is_active', true)
            ->whereDate('valid_from', '<=', $today)
            ->where(function ($query) use ($today) {
                $query->whereNull('valid_to')
                    ->orWhereDate('valid_to', '>=', $today);
            })
            ->where(function ($query) use ($today) {
                $query->whereNull('available_from')
                    ->orWhereDate('available_from', '<=', $today);
            })
            ->where(function ($query) use ($today) {
                $query->whereNull('available_to')
                    ->orWhereDate('available_to', '>=', $today);
            })
            ->where(function ($query) use ($shootingRange) {
                $query->where('shooting_range_id', 0)
                    ->orWhere('shooting_range_id', $shootingRange->id);
            })
            ->when(! $includeSpecial, function ($query) {
                $query
                    ->where('is_public', true)
                    ->where('requires_superadmin_approval', false)
                    ->where('type', 'standard');
            })
            ->orderByDesc('shooting_range_id')
            ->orderBy('period_months')
            ->get()
            ->unique(fn (ShootingRangePackage $package) => $package->period_months.'-'.$package->type)
            ->values();
    }

    public function unpaidChargesExist(ShootingRange $shootingRange): bool
    {
        return $shootingRange->billingCharges()
            ->where('status', BillingCharge::STATUS_UNPAID)
            ->where(function ($query) {
                $query->whereNull('package_order_id')
                    ->orWhereHas('packageOrder', function ($orderQuery) {
                        $orderQuery->where('status', '!=', PackageOrder::STATUS_CANCELLED);
                    });
            })
            ->exists();
    }

    public function isOrderPayable(PackageOrder $order): bool
    {
        if (
            $order->status !== PackageOrder::STATUS_CONDITIONAL_ACTIVE
            || $order->payment_status !== PackageOrder::PAYMENT_PENDING
            || $order->payment_method !== PackageOrder::METHOD_P24
            || $order->payment_due_at?->isPast()
            || ! $order->package
            || ! $order->shootingRange
        ) {
            return false;
        }

        return $this->availablePackagesFor($order->shootingRange)
            ->contains(fn (ShootingRangePackage $package): bool => $package->id === $order->shooting_range_package_id);
    }

    public function cancelUnpaidOrder(PackageOrder $order, ?User $actor = null, ?string $note = null): void
    {
        if ($order->payment_status === PackageOrder::PAYMENT_PAID) {
            return;
        }

        DB::transaction(function () use ($order, $actor, $note): void {
            $order->update([
                'status' => PackageOrder::STATUS_CANCELLED,
                'payment_status' => PackageOrder::PAYMENT_CANCELLED,
                'cancelled_at' => now(),
                'cancelled_by_user_id' => $actor?->id,
                'internal_note' => trim(($order->internal_note ? $order->internal_note.PHP_EOL : '').($note ?? '')),
            ]);

            $order->payments()
                ->where('status', '!=', Payment::STATUS_PAID)
                ->update([
                    'status' => Payment::STATUS_FAILED,
                    'failed_at' => now(),
                    'note' => $note,
                ]);

            $order->billingCharges()
                ->where('status', BillingCharge::STATUS_UNPAID)
                ->update([
                    'status' => BillingCharge::STATUS_CANCELLED,
                    'note' => trim('Anulowano razem z zamowieniem abonamentu.'.($note ? PHP_EOL.$note : '')),
                ]);

            $this->recordOrderIntervalEvent($order, ShootingRangeIntervalEvent::TYPE_ORDER_CANCELLED, $note);
            $this->recalculateOperationalInterval($order->shootingRange, $actor, $note);
        });
    }

    public function cancelUnpaidOrdersForPackage(ShootingRangePackage $package, ?User $actor = null): int
    {
        $orders = $package->orders()
            ->with(['interval', 'payments'])
            ->where('payment_status', PackageOrder::PAYMENT_PENDING)
            ->where('status', PackageOrder::STATUS_CONDITIONAL_ACTIVE)
            ->get();

        foreach ($orders as $order) {
            $this->cancelUnpaidOrder($order, $actor, 'Pakiet zostal wycofany przez superadmina.');
        }

        return $orders->count();
    }

    public function previewOrderPeriod(
        ShootingRange $shootingRange,
        ShootingRangePackage $package,
        ?Carbon $startsAt = null
    ): array {
        $startsAt ??= $this->nextStartDate($shootingRange);
        $endsAt = $startsAt->copy()
            ->addMonthsNoOverflow($package->period_months + $package->bonus_months)
            ->subDay();

        return [
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ];
    }

    public function transferTitleFor(PackageOrder $order): string
    {
        return 'ABONAMENT STRZELNICE.ORG/'.$order->id.'/'.$order->shooting_range_id;
    }

    public function createOrder(
        ShootingRange $shootingRange,
        ShootingRangePackage $package,
        User $actor,
        string $paymentMethod,
        ?Carbon $startsAt = null,
        ?float $manualAmount = null,
        ?string $note = null,
        ?string $manualReason = null
    ): PackageOrder {
        if ($this->unpaidChargesExist($shootingRange)) {
            throw ValidationException::withMessages([
                'package_id' => 'Strzelnica ma nieoplacona naleznosc za poprzedni dostep warunkowy.',
            ]);
        }

        $configuration = SystemConfiguration::activeConfiguration();
        $dueDays = max(1, (int) ($configuration?->subscription_payment_due_days ?? 7));
        $amount = $manualAmount ?? (float) ($package->price_gross ?? $package->value);
        $period = $this->previewOrderPeriod($shootingRange, $package, $startsAt);
        $startsAt = $period['starts_at'];
        $endsAt = $period['ends_at'];

        return DB::transaction(function () use (
            $shootingRange,
            $package,
            $actor,
            $paymentMethod,
            $startsAt,
            $endsAt,
            $dueDays,
            $amount,
            $note,
            $manualReason
        ) {
            $paymentStatus = $amount <= 0 ? PackageOrder::PAYMENT_PAID : PackageOrder::PAYMENT_PENDING;
            $status = $paymentStatus === PackageOrder::PAYMENT_PAID
                ? PackageOrder::STATUS_ACTIVE
                : PackageOrder::STATUS_CONDITIONAL_ACTIVE;

            $order = PackageOrder::create([
                'shooting_range_id' => $shootingRange->id,
                'shooting_range_package_id' => $package->id,
                'ordered_by_user_id' => $actor->id,
                'created_by_user_id' => $actor->id,
                'status' => $status,
                'payment_method' => $paymentMethod,
                'payment_status' => $paymentStatus,
                'amount_gross' => $amount,
                'currency' => $package->currency ?: 'PLN',
                'period_months' => $package->period_months,
                'bonus_months' => $package->bonus_months,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'payment_due_at' => now()->addDays($dueDays),
                'paid_at' => $paymentStatus === PackageOrder::PAYMENT_PAID ? now() : null,
                'activated_at' => now(),
                'activated_by_user_id' => $paymentStatus === PackageOrder::PAYMENT_PAID ? $actor->id : null,
                'notes' => $note,
                'manual_reason' => $manualReason,
            ]);

            $payment = Payment::create([
                'payable_type' => PackageOrder::class,
                'payable_id' => $order->id,
                'package_order_id' => $order->id,
                'provider' => $paymentMethod === PackageOrder::METHOD_P24 ? 'przelewy24' : 'manual',
                'method' => $paymentMethod,
                'status' => $paymentStatus === PackageOrder::PAYMENT_PAID ? Payment::STATUS_PAID : Payment::STATUS_CREATED,
                'amount' => $amount,
                'amount_minor' => (int) round($amount * 100),
                'currency' => $package->currency ?: 'PLN',
                'description' => 'Abonament strzelnicy: '.$shootingRange->name,
                'payer_email' => $actor->email,
                'payer_name' => $actor->name,
                'p24_session_id' => 'pkg-'.$order->id.'-'.Str::random(24),
                'confirmed_by_user_id' => $paymentStatus === PackageOrder::PAYMENT_PAID ? $actor->id : null,
                'confirmed_at' => $paymentStatus === PackageOrder::PAYMENT_PAID ? now() : null,
                'note' => $note,
            ]);

            if ($paymentMethod === PackageOrder::METHOD_BANK_TRANSFER && $paymentStatus === PackageOrder::PAYMENT_PENDING) {
                $transferTitle = $this->transferTitleFor($order);

                $order->update([
                    'payment_note' => $transferTitle,
                ]);

                $payment->update([
                    'note' => $transferTitle,
                ]);
            }

            $interval = $this->extendOperationalIntervalFromOrder($order, $payment, $actor);
            $this->syncArchive($interval);

            return $order->fresh(['payments', 'shootingRange', 'package']);
        });
    }

    public function markOrderPaid(PackageOrder $order, Payment $payment, ?User $actor = null, ?string $note = null): void
    {
        $canBeManuallyPaid = in_array($order->payment_method, [
            PackageOrder::METHOD_BANK_TRANSFER,
            PackageOrder::METHOD_CASH,
            PackageOrder::METHOD_FREE,
        ], true)
            && $order->status === PackageOrder::STATUS_CONDITIONAL_ACTIVE
            && $order->payment_status === PackageOrder::PAYMENT_PENDING;

        if (! $this->isOrderPayable($order) && ! $canBeManuallyPaid && $order->status !== PackageOrder::STATUS_EXPIRED) {
            throw ValidationException::withMessages([
                'payment' => 'Tego zamowienia nie mozna juz oplacic, poniewaz pakiet zostal wycofany albo zamowienie nie jest aktywne.',
            ]);
        }

        DB::transaction(function () use ($order, $payment, $actor, $note): void {
            $wasExpired = $order->status === PackageOrder::STATUS_EXPIRED;

            $payment->update([
                'status' => Payment::STATUS_PAID,
                'verified_at' => now(),
                'confirmed_by_user_id' => $actor?->id,
                'confirmed_at' => now(),
                'confirmation_note' => $note,
            ]);

            $order->update([
                'status' => $wasExpired ? PackageOrder::STATUS_EXPIRED : PackageOrder::STATUS_ACTIVE,
                'payment_status' => PackageOrder::PAYMENT_PAID,
                'paid_at' => now(),
                'activated_at' => $wasExpired ? $order->activated_at : ($order->activated_at ?? now()),
                'activated_by_user_id' => $wasExpired ? $order->activated_by_user_id : ($actor?->id ?? $order->activated_by_user_id),
                'payment_note' => $order->payment_method === PackageOrder::METHOD_BANK_TRANSFER
                    ? $order->payment_note
                    : $note,
            ]);

            if ($order->interval && ! $wasExpired) {
                $order->interval->update([
                    'datePaid' => now(),
                    'is_active' => true,
                    'subscription_status' => PackageOrder::STATUS_ACTIVE,
                ]);
                $this->syncArchive($order->interval);
            }

            $this->recordOrderIntervalEvent($order, ShootingRangeIntervalEvent::TYPE_ORDER_PAID, $note);
            $this->recalculateOperationalInterval($order->shootingRange, $actor, $note);

            $order->billingCharges()
                ->where('status', BillingCharge::STATUS_UNPAID)
                ->update([
                    'status' => BillingCharge::STATUS_PAID,
                    'paid_at' => now(),
                    'settled_by_user_id' => $actor?->id,
                ]);
        });
    }

    public function expireUnpaidOrder(PackageOrder $order): void
    {
        if ($order->payment_status === PackageOrder::PAYMENT_PAID || ! $order->payment_due_at?->isPast()) {
            return;
        }

        DB::transaction(function () use ($order): void {
            $order->update([
                'status' => PackageOrder::STATUS_EXPIRED,
                'payment_status' => PackageOrder::PAYMENT_FAILED,
                'expired_at' => now(),
            ]);

            $this->recordOrderIntervalEvent($order, ShootingRangeIntervalEvent::TYPE_ORDER_EXPIRED, 'Upłynął termin płatności zamówienia.');
            $this->recalculateOperationalInterval($order->shootingRange, note: 'Upłynął termin płatności zamówienia.');

            if (! $order->billingCharges()->where('type', 'conditional_access_fee')->exists()) {
                $amount = $this->conditionalAccessAmount($order);
                $configuration = SystemConfiguration::activeConfiguration();

                BillingCharge::create([
                    'shooting_range_id' => $order->shooting_range_id,
                    'package_order_id' => $order->id,
                    'type' => 'conditional_access_fee',
                    'status' => BillingCharge::STATUS_UNPAID,
                    'amount' => $amount,
                    'currency' => $order->currency,
                    'period_from' => $order->starts_at,
                    'period_to' => $order->payment_due_at->copy()->startOfDay()->lessThan($order->ends_at)
                        ? $order->payment_due_at->copy()->toDateString()
                        : $order->ends_at->toDateString(),
                    'due_at' => now()->addDays((int) ($configuration?->conditional_access_charge_due_days ?? 7)),
                    'description' => 'Naleznosc za warunkowy dostep do abonamentu.',
                ]);
            }
        });
    }

    public function syncArchive(ShootingRangeInterval $interval): void
    {
        ShootingRangeIntervalArchive::updateOrCreate(
            ['shooting_range_interval_id' => $interval->id],
            [
                'shooting_range_id' => $interval->shooting_range_id,
                'package_order_id' => $interval->package_order_id,
                'payment_id' => $interval->payment_id,
                'start_date' => $interval->start_date,
                'end_date' => $interval->end_date,
                'operator_insert_id' => $interval->operator_insert_id,
                'operator_update_id' => $interval->operator_update_id,
                'value' => $interval->value,
                'datePaid' => $interval->datePaid,
                'payment_due_date' => $interval->payment_due_date,
                'is_active' => $interval->is_active,
                'source' => $interval->source,
                'subscription_status' => $interval->subscription_status,
                'activated_by_user_id' => $interval->activated_by_user_id,
                'activated_at' => $interval->activated_at,
                'blocked_at' => $interval->blocked_at,
            ]
        );
    }

    public function extendManualAccess(
        ShootingRange $shootingRange,
        Carbon $startsAt,
        Carbon $endsAt,
        ?User $actor = null,
        ?string $note = null
    ): ShootingRangeInterval {
        return DB::transaction(function () use ($shootingRange, $startsAt, $endsAt, $actor, $note): ShootingRangeInterval {
            $interval = $this->operationalIntervalFor(
                $shootingRange,
                $startsAt,
                $actor,
                ShootingRangeIntervalEvent::SOURCE_MANUAL
            );
            $previousStartDate = $interval->start_date?->copy();
            $previousEndDate = $interval->end_date?->copy();
            $newStartDate = $interval->start_date->lessThanOrEqualTo($startsAt)
                ? $interval->start_date
                : $startsAt->copy()->startOfDay();
            $newEndDate = $interval->end_date->greaterThanOrEqualTo($endsAt)
                ? $interval->end_date
                : $endsAt->copy()->startOfDay();

            $source = $interval->source === ShootingRangeIntervalEvent::SOURCE_PACKAGE_ORDER
                ? 'mixed'
                : ShootingRangeIntervalEvent::SOURCE_MANUAL;

            $interval->update([
                'start_date' => $newStartDate,
                'end_date' => $newEndDate,
                'is_active' => true,
                'source' => $source,
                'subscription_status' => PackageOrder::STATUS_ACTIVE,
                'datePaid' => $interval->datePaid ?? now(),
                'payment_due_date' => null,
                'operator_update_id' => $actor?->id,
                'activated_by_user_id' => $interval->activated_by_user_id ?? $actor?->id,
                'activated_at' => $interval->activated_at ?? now(),
                'blocked_at' => null,
            ]);

            ShootingRangeIntervalEvent::create([
                'shooting_range_interval_id' => $interval->id,
                'shooting_range_id' => $shootingRange->id,
                'created_by_user_id' => $actor?->id,
                'type' => ShootingRangeIntervalEvent::TYPE_MANUAL_EXTENDED,
                'source' => ShootingRangeIntervalEvent::SOURCE_MANUAL,
                'date_from' => $startsAt,
                'date_to' => $endsAt,
                'previous_start_date' => $previousStartDate,
                'previous_end_date' => $previousEndDate,
                'new_start_date' => $interval->start_date,
                'new_end_date' => $interval->end_date,
                'note' => $note,
            ]);

            $this->syncArchive($interval);

            return $interval->fresh(['events', 'packageOrders']);
        });
    }

    private function nextStartDate(ShootingRange $shootingRange): Carbon
    {
        $latestInterval = $shootingRange->intervals()
            ->where('is_active', true)
            ->orderByDesc('end_date')
            ->first();

        if ($latestInterval && $latestInterval->end_date->greaterThanOrEqualTo(today())) {
            return $latestInterval->end_date->copy()->addDay()->startOfDay();
        }

        return today();
    }

    private function extendOperationalIntervalFromOrder(PackageOrder $order, Payment $payment, User $actor): ShootingRangeInterval
    {
        $interval = $this->operationalIntervalFor(
            $order->shootingRange,
            $order->starts_at,
            $actor,
            ShootingRangeIntervalEvent::SOURCE_PACKAGE_ORDER
        );
        $previousStartDate = $interval->start_date?->copy();
        $previousEndDate = $interval->end_date?->copy();
        $newStartDate = $interval->start_date->lessThanOrEqualTo($order->starts_at)
            ? $interval->start_date
            : $order->starts_at->copy()->startOfDay();
        $newEndDate = $interval->end_date->greaterThanOrEqualTo($order->ends_at)
            ? $interval->end_date
            : $order->ends_at->copy()->startOfDay();

        $interval->update([
            'package_order_id' => $interval->package_order_id ?? $order->id,
            'payment_id' => $interval->payment_id ?? $payment->id,
            'start_date' => $newStartDate,
            'end_date' => $newEndDate,
            'value' => (float) $interval->value + (float) $order->amount_gross,
            'payment_due_date' => $order->payment_due_at,
            'datePaid' => $order->payment_status === PackageOrder::PAYMENT_PAID ? now() : $interval->datePaid,
            'is_active' => true,
            'source' => $interval->source === ShootingRangeIntervalEvent::SOURCE_MANUAL
                ? 'mixed'
                : ShootingRangeIntervalEvent::SOURCE_PACKAGE_ORDER,
            'subscription_status' => $order->status,
            'activated_by_user_id' => $interval->activated_by_user_id ?? $actor->id,
            'activated_at' => $interval->activated_at ?? now(),
            'blocked_at' => null,
        ]);

        $order->update(['shooting_range_interval_id' => $interval->id]);

        ShootingRangeIntervalEvent::create([
            'shooting_range_interval_id' => $interval->id,
            'shooting_range_id' => $order->shooting_range_id,
            'package_order_id' => $order->id,
            'payment_id' => $payment->id,
            'created_by_user_id' => $actor->id,
            'type' => ShootingRangeIntervalEvent::TYPE_ORDER_CREATED,
            'source' => ShootingRangeIntervalEvent::SOURCE_PACKAGE_ORDER,
            'date_from' => $order->starts_at,
            'date_to' => $order->ends_at,
            'previous_start_date' => $previousStartDate,
            'previous_end_date' => $previousEndDate,
            'new_start_date' => $interval->start_date,
            'new_end_date' => $interval->end_date,
            'amount' => $order->amount_gross,
            'currency' => $order->currency,
            'payment_status' => $order->payment_status,
            'payment_method' => $order->payment_method,
            'note' => $order->manual_reason ?: $order->notes,
        ]);

        return $interval->fresh();
    }

    public function recalculateOperationalInterval(ShootingRange $shootingRange, ?User $actor = null, ?string $note = null): ?ShootingRangeInterval
    {
        return DB::transaction(function () use ($shootingRange, $actor, $note): ?ShootingRangeInterval {
            $interval = $shootingRange->intervals()
                ->where('is_active', true)
                ->orderByDesc('end_date')
                ->lockForUpdate()
                ->first();

            if (! $interval) {
                return null;
            }

            $validOrders = $shootingRange->packageOrders()
                ->where('shooting_range_interval_id', $interval->id)
                ->whereNotIn('payment_status', [
                    PackageOrder::PAYMENT_FAILED,
                    PackageOrder::PAYMENT_CANCELLED,
                ])
                ->where('status', '!=', PackageOrder::STATUS_CANCELLED)
                ->where(function ($query) {
                    $query
                        ->where('payment_status', PackageOrder::PAYMENT_PAID)
                        ->orWhere(function ($pendingQuery) {
                            $pendingQuery
                                ->where('payment_status', PackageOrder::PAYMENT_PENDING)
                                ->where(function ($dueQuery) {
                                    $dueQuery
                                        ->whereNull('payment_due_at')
                                        ->orWhere('payment_due_at', '>=', now());
                                });
                        });
                })
                ->get(['starts_at', 'ends_at', 'amount_gross']);

            $manualEvents = $interval->events()
                ->where('type', ShootingRangeIntervalEvent::TYPE_MANUAL_EXTENDED)
                ->get(['date_from', 'date_to']);

            $dateRanges = collect()
                ->merge($validOrders->map(fn (PackageOrder $order): array => [
                    'from' => $order->starts_at,
                    'to' => $order->ends_at,
                ]))
                ->merge($manualEvents->map(fn (ShootingRangeIntervalEvent $event): array => [
                    'from' => $event->date_from,
                    'to' => $event->date_to,
                ]))
                ->filter(fn (array $range): bool => $range['from'] && $range['to'] && $range['to']->greaterThanOrEqualTo(today()));

            $previousStartDate = $interval->start_date?->copy();
            $previousEndDate = $interval->end_date?->copy();

            if ($dateRanges->isEmpty()) {
                $intervalUpdate = [
                    'is_active' => false,
                    'subscription_status' => PackageOrder::STATUS_EXPIRED,
                    'blocked_at' => now(),
                ];

                if ($actor) {
                    $intervalUpdate['operator_update_id'] = $actor->id;
                }

                $interval->update($intervalUpdate);

                ShootingRangeIntervalEvent::create([
                    'shooting_range_interval_id' => $interval->id,
                    'shooting_range_id' => $shootingRange->id,
                    'created_by_user_id' => $actor?->id,
                    'type' => ShootingRangeIntervalEvent::TYPE_INTERVAL_RECALCULATED,
                    'source' => $interval->source,
                    'previous_start_date' => $previousStartDate,
                    'previous_end_date' => $previousEndDate,
                    'new_start_date' => $interval->start_date,
                    'new_end_date' => $interval->end_date,
                    'amount' => 0,
                    'note' => $note,
                ]);

                $this->syncArchive($interval);

                return $interval->fresh();
            }

            $newStartDate = $dateRanges
                ->sortBy(fn (array $range) => $range['from']->timestamp)
                ->first()['from']
                ->copy()
                ->startOfDay();
            $newEndDate = $dateRanges
                ->sortByDesc(fn (array $range) => $range['to']->timestamp)
                ->first()['to']
                ->copy()
                ->startOfDay();

            $intervalUpdate = [
                'start_date' => $newStartDate,
                'end_date' => $newEndDate,
                'value' => $validOrders->sum('amount_gross'),
                'is_active' => true,
                'source' => $manualEvents->isNotEmpty() && $validOrders->isNotEmpty()
                    ? 'mixed'
                    : ($manualEvents->isNotEmpty() ? ShootingRangeIntervalEvent::SOURCE_MANUAL : ShootingRangeIntervalEvent::SOURCE_PACKAGE_ORDER),
                'subscription_status' => PackageOrder::STATUS_ACTIVE,
                'blocked_at' => null,
            ];

            if ($actor) {
                $intervalUpdate['operator_update_id'] = $actor->id;
            }

            $interval->update($intervalUpdate);

            ShootingRangeIntervalEvent::create([
                'shooting_range_interval_id' => $interval->id,
                'shooting_range_id' => $shootingRange->id,
                'created_by_user_id' => $actor?->id,
                'type' => ShootingRangeIntervalEvent::TYPE_INTERVAL_RECALCULATED,
                'source' => $manualEvents->isNotEmpty() && $validOrders->isNotEmpty()
                    ? 'mixed'
                    : ($manualEvents->isNotEmpty() ? ShootingRangeIntervalEvent::SOURCE_MANUAL : ShootingRangeIntervalEvent::SOURCE_PACKAGE_ORDER),
                'previous_start_date' => $previousStartDate,
                'previous_end_date' => $previousEndDate,
                'new_start_date' => $interval->start_date,
                'new_end_date' => $interval->end_date,
                'amount' => $validOrders->sum('amount_gross'),
                'note' => $note,
            ]);

            $this->syncArchive($interval);

            return $interval->fresh();
        });
    }

    private function operationalIntervalFor(
        ShootingRange $shootingRange,
        Carbon $fallbackStartDate,
        ?User $actor = null,
        string $source = ShootingRangeIntervalEvent::SOURCE_MANUAL
    ): ShootingRangeInterval {
        $activeIntervals = $shootingRange->intervals()
            ->where('is_active', true)
            ->orderByDesc('end_date')
            ->lockForUpdate()
            ->get();

        $interval = $activeIntervals->first();

        $activeIntervals
            ->skip(1)
            ->each(function (ShootingRangeInterval $extraInterval) use ($actor): void {
                $extraInterval->update([
                    'is_active' => false,
                    'operator_update_id' => $actor?->id,
                    'blocked_at' => now(),
                ]);
                $this->syncArchive($extraInterval);
            });

        if ($interval) {
            return $interval;
        }

        return ShootingRangeInterval::create([
            'shooting_range_id' => $shootingRange->id,
            'start_date' => $fallbackStartDate->copy()->startOfDay(),
            'end_date' => $fallbackStartDate->copy()->startOfDay(),
            'value' => 0,
            'is_active' => true,
            'source' => $source,
            'subscription_status' => PackageOrder::STATUS_ACTIVE,
            'activated_by_user_id' => $actor?->id,
            'activated_at' => now(),
        ]);
    }

    private function recordOrderIntervalEvent(PackageOrder $order, string $type, ?string $note = null): void
    {
        if (! $order->shooting_range_interval_id) {
            return;
        }

        ShootingRangeIntervalEvent::create([
            'shooting_range_interval_id' => $order->shooting_range_interval_id,
            'shooting_range_id' => $order->shooting_range_id,
            'package_order_id' => $order->id,
            'payment_id' => $order->payments()->latest()->value('id'),
            'created_by_user_id' => auth()->id(),
            'type' => $type,
            'source' => ShootingRangeIntervalEvent::SOURCE_PACKAGE_ORDER,
            'date_from' => $order->starts_at,
            'date_to' => $order->ends_at,
            'amount' => $order->amount_gross,
            'currency' => $order->currency,
            'payment_status' => $order->payment_status,
            'payment_method' => $order->payment_method,
            'note' => $note,
        ]);
    }

    private function conditionalAccessAmount(PackageOrder $order): float
    {
        $configuration = SystemConfiguration::activeConfiguration();

        if (($configuration?->conditional_access_charge_mode ?? 'proportional') === 'full') {
            return (float) $order->amount_gross;
        }

        $totalDays = max(1, $order->starts_at->diffInDays($order->ends_at) + 1);
        $usedUntil = min($order->payment_due_at->copy()->startOfDay(), $order->ends_at->copy()->startOfDay());
        $usedDays = max(1, $order->starts_at->diffInDays($usedUntil) + 1);

        return round(((float) $order->amount_gross / $totalDays) * $usedDays, 2);
    }
}

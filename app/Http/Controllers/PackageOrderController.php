<?php

namespace App\Http\Controllers;

use App\Models\PackageOrder;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Models\Reservation;
use App\Models\ShootingRange;
use App\Models\ShootingRangePackage;
use App\Models\SystemConfiguration;
use App\Services\Przelewy24Service;
use App\Services\ReservationPaymentService;
use App\Services\ShootingRangeSubscriptionService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class PackageOrderController extends Controller
{
    public function index(
        Request $request,
        ShootingRangeSubscriptionService $subscriptions
    ): View {
        $shootingRange = $this->managedShootingRange($request);
        $packages = collect();
        $orders = collect();
        $charges = collect();
        $hasUnpaidCharges = false;
        $configuration = SystemConfiguration::activeConfiguration();
        $p24Enabled = (bool) ($configuration?->p24_enabled);

        if ($shootingRange) {
            $packages = $subscriptions->availablePackagesFor($shootingRange);
            $packagePreviews = $packages
                ->mapWithKeys(function (ShootingRangePackage $package) use ($shootingRange, $subscriptions): array {
                    $period = $subscriptions->previewOrderPeriod($shootingRange, $package);

                    return [
                        $package->id => [
                            'starts_at' => $period['starts_at']->format('Y-m-d'),
                            'ends_at' => $period['ends_at']->format('Y-m-d'),
                        ],
                    ];
                })
                ->all();
            $orders = $shootingRange->packageOrders()
                ->with(['package', 'payments', 'shootingRange'])
                ->where('status', '!=', PackageOrder::STATUS_CANCELLED)
                ->latest()
                ->limit(20)
                ->get();
            $payableOrderIds = $orders
                ->filter(fn (PackageOrder $order): bool => $subscriptions->isOrderPayable($order))
                ->pluck('id')
                ->all();
            $charges = $shootingRange->billingCharges()
                ->latest()
                ->limit(20)
                ->get();
            $hasUnpaidCharges = $subscriptions->unpaidChargesExist($shootingRange);
        } else {
            $packagePreviews = [];
            $payableOrderIds = [];
        }

        return view('admin.subscriptions.index', compact(
            'shootingRange',
            'packages',
            'orders',
            'charges',
            'configuration',
            'hasUnpaidCharges',
            'p24Enabled',
            'packagePreviews',
            'payableOrderIds'
        ));
    }

    public function adminIndex(
        Request $request,
        ShootingRangeSubscriptionService $subscriptions
    ): View {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', 'in:shooting_range,package,amount_gross,payment_status,payment_method,payment_due_at,starts_at,created_at'],
            'direction' => ['nullable', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'in:10,20,50'],
            'payment_statuses' => ['nullable', 'array'],
            'payment_statuses.*' => ['in:pending,paid,failed,cancelled'],
            'payment_methods' => ['nullable', 'array'],
            'payment_methods.*' => ['in:p24,bank_transfer,cash,free'],
            'due_states' => ['nullable', 'array'],
            'due_states.*' => ['in:on_time,overdue'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $sort = $validated['sort'] ?? 'created_at';
        $direction = $validated['direction'] ?? 'desc';
        $perPage = (int) ($validated['per_page'] ?? 20);
        $paymentStatuses = $validated['payment_statuses'] ?? [];
        $paymentMethods = $validated['payment_methods'] ?? [];
        $dueStates = $validated['due_states'] ?? [];

        $shootingRanges = ShootingRange::query()
            ->where('is_archived', false)
            ->orderBy('name')
            ->get();

        $selectedShootingRange = $request->integer('shooting_range_id')
            ? ShootingRange::find($request->integer('shooting_range_id'))
            : $shootingRanges->first();

        $packages = $selectedShootingRange
            ? $subscriptions->availablePackagesFor($selectedShootingRange, includeSpecial: true)
            : collect();

        $manualPackagesByShootingRange = $shootingRanges
            ->mapWithKeys(function (ShootingRange $shootingRange) use ($subscriptions) {
                return [
                    $shootingRange->id => $subscriptions
                        ->availablePackagesFor($shootingRange, includeSpecial: true)
                        ->map(fn (ShootingRangePackage $package): array => [
                            'id' => $package->id,
                            'display_name' => $package->display_name,
                            'price' => number_format((float) $package->effective_price, 2, ',', ' '),
                            'currency' => $package->currency,
                            'label' => $package->display_name.' - '.number_format((float) $package->effective_price, 2, ',', ' ').' '.$package->currency,
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->all();

        $ordersQuery = PackageOrder::query()
            ->with(['shootingRange', 'package', 'orderedBy', 'payments', 'interval.events.createdBy']);

        foreach (preg_split('/\s+/u', $search, -1, PREG_SPLIT_NO_EMPTY) as $term) {
            $ordersQuery->where(function ($query) use ($term) {
                $query
                    ->where('payment_status', 'like', "%{$term}%")
                    ->orWhere('payment_method', 'like', "%{$term}%")
                    ->orWhere('amount_gross', 'like', "%{$term}%")
                    ->orWhere('payment_due_at', 'like', "%{$term}%")
                    ->orWhere('notes', 'like', "%{$term}%")
                    ->orWhere('internal_note', 'like', "%{$term}%")
                    ->orWhere('payment_note', 'like', "%{$term}%")
                    ->orWhereHas('shootingRange', function ($rangeQuery) use ($term) {
                        $rangeQuery
                            ->where('name', 'like', "%{$term}%")
                            ->orWhere('town', 'like', "%{$term}%")
                            ->orWhere('street', 'like', "%{$term}%")
                            ->orWhere('zipCode', 'like', "%{$term}%")
                            ->orWhere('voivodeship', 'like', "%{$term}%");
                    })
                    ->orWhereHas('package', function ($packageQuery) use ($term) {
                        $packageQuery
                            ->where('name', 'like', "%{$term}%")
                            ->orWhere('period_months', 'like', "%{$term}%")
                            ->orWhere('value', 'like', "%{$term}%");
                    });
            });
        }

        if ($paymentStatuses !== []) {
            $ordersQuery->whereIn('payment_status', $paymentStatuses);
        }

        if ($paymentMethods !== []) {
            $ordersQuery->whereIn('payment_method', $paymentMethods);
        }

        if ($dueStates !== []) {
            $ordersQuery
                ->where('status', '!=', PackageOrder::STATUS_CANCELLED)
                ->whereNotIn('payment_status', [
                    PackageOrder::PAYMENT_PAID,
                    PackageOrder::PAYMENT_CANCELLED,
                ])
                ->where(function ($query) use ($dueStates) {
                    if (in_array('on_time', $dueStates, true)) {
                        $query->orWhere(function ($dueQuery) {
                            $dueQuery
                                ->whereNull('payment_due_at')
                                ->orWhere('payment_due_at', '>=', now());
                        });
                    }

                    if (in_array('overdue', $dueStates, true)) {
                        $query->orWhere(function ($dueQuery) {
                            $dueQuery
                                ->whereNotNull('payment_due_at')
                                ->where('payment_due_at', '<', now());
                        });
                    }
                });
        }

        match ($sort) {
            'shooting_range' => $ordersQuery
                ->leftJoin('shooting_ranges', 'package_orders.shooting_range_id', '=', 'shooting_ranges.id')
                ->select('package_orders.*')
                ->orderBy('shooting_ranges.name', $direction)
                ->orderBy('package_orders.id', $direction),
            'package' => $ordersQuery
                ->leftJoin('shooting_range_packages', 'package_orders.shooting_range_package_id', '=', 'shooting_range_packages.id')
                ->select('package_orders.*')
                ->orderBy('shooting_range_packages.period_months', $direction)
                ->orderBy('shooting_range_packages.name', $direction)
                ->orderBy('package_orders.id', $direction),
            default => $ordersQuery
                ->orderBy($sort, $direction)
                ->orderBy('id', $direction),
        };

        $orders = $ordersQuery
            ->paginate($perPage)
            ->withQueryString();
        $payableOrderIds = $orders
            ->getCollection()
            ->filter(fn (PackageOrder $order): bool => $subscriptions->isOrderPayable($order))
            ->pluck('id')
            ->all();

        return view('superAdmin.subscriptions.index', compact(
            'shootingRanges',
            'selectedShootingRange',
            'packages',
            'manualPackagesByShootingRange',
            'orders',
            'payableOrderIds',
            'search',
            'sort',
            'direction',
            'perPage',
            'paymentStatuses',
            'paymentMethods',
            'dueStates'
        ));
    }

    public function store(
        Request $request,
        ShootingRangeSubscriptionService $subscriptions,
        Przelewy24Service $przelewy24
    ): RedirectResponse {
        $shootingRange = $this->managedShootingRange($request);

        abort_unless($shootingRange, 403);

        $validated = $request->validate([
            'package_id' => ['required', 'integer', 'exists:shooting_range_packages,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'terms_accepted' => ['accepted'],
        ], [
            'terms_accepted.accepted' => 'Potwierdź zapoznanie się z regulaminem partnera.',
        ]);

        $package = $subscriptions->availablePackagesFor($shootingRange)
            ->firstWhere('id', (int) $validated['package_id']);

        if (! $package) {
            return back()->with('error', 'Wybrany pakiet nie jest dostepny dla tej strzelnicy.');
        }

        try {
            $configuration = SystemConfiguration::activeConfiguration();
            $paymentMethod = $configuration?->p24_enabled
                ? PackageOrder::METHOD_P24
                : PackageOrder::METHOD_BANK_TRANSFER;

            if ($paymentMethod === PackageOrder::METHOD_P24) {
                $przelewy24->assertConfigured();
            } elseif (blank($configuration?->bank_account_number)) {
                throw ValidationException::withMessages([
                    'payment' => 'Nie skonfigurowano numeru rachunku bankowego do przelewu tradycyjnego.',
                ]);
            }

            $order = $subscriptions->createOrder(
                $shootingRange,
                $package,
                $request->user(),
                $paymentMethod,
                note: $validated['notes'] ?? null
            );

            if ($paymentMethod === PackageOrder::METHOD_BANK_TRANSFER) {
                return redirect()
                    ->route('shooting.subscription.index')
                    ->with('success', 'Zamówienie abonamentu zostało utworzone. Dane do przelewu znajdziesz przy zamówieniu na liście abonamentów.');
            }

            return $this->redirectToPayment($order, $przelewy24);
        } catch (ValidationException $exception) {
            return back()
                ->withInput()
                ->with('error', $this->paymentErrorMessage($exception));
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput()
                ->with('error', 'Nie udalo sie przekierowac do platnosci. Sprawdz konfiguracje Przelewy24 i sprobuj ponownie.');
        }
    }

    public function storeManual(
        Request $request,
        ShootingRangeSubscriptionService $subscriptions
    ): RedirectResponse {
        $validated = $request->validate([
            'shooting_range_id' => ['required', 'integer', 'exists:shooting_ranges,id'],
            'package_id' => ['required', 'integer', 'exists:shooting_range_packages,id'],
            'payment_method' => ['required', 'in:bank_transfer,cash,free'],
            'amount_gross' => ['nullable', 'numeric', 'min:0'],
            'starts_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'manual_reason' => ['required', 'string', 'max:2000'],
        ]);

        $shootingRange = ShootingRange::findOrFail($validated['shooting_range_id']);
        $package = $subscriptions
            ->availablePackagesFor($shootingRange, includeSpecial: true)
            ->firstWhere('id', (int) $validated['package_id']);

        if (! $package) {
            return back()
                ->withInput()
                ->with('error', 'Wybrany pakiet nie jest dostepny dla tej strzelnicy.');
        }

        $subscriptions->createOrder(
            $shootingRange,
            $package,
            $request->user(),
            $validated['payment_method'],
            startsAt: filled($validated['starts_at'] ?? null) ? Carbon::parse($validated['starts_at']) : null,
            manualAmount: array_key_exists('amount_gross', $validated) && $validated['amount_gross'] !== null
                ? (float) $validated['amount_gross']
                : null,
            note: $validated['notes'] ?? null,
            manualReason: $validated['manual_reason']
        );

        return back()->with('success', 'Utworzono zamowienie abonamentu.');
    }

    public function pay(PackageOrder $packageOrder, Przelewy24Service $przelewy24): RedirectResponse
    {
        abort_if($packageOrder->payment_method !== PackageOrder::METHOD_P24, 404);
        abort_unless($this->canManageOrder(request(), $packageOrder), 403);

        $subscriptions = app(ShootingRangeSubscriptionService::class);

        if (! $subscriptions->isOrderPayable($packageOrder->loadMissing(['package', 'shootingRange']))) {
            return redirect()
                ->route('shooting.subscription.index')
                ->with('error', 'Tego zamówienia nie można już opłacić, ponieważ pakiet został wycofany albo zamówienie nie jest aktywne.');
        }

        try {
            return $this->redirectToPayment($packageOrder, $przelewy24);
        } catch (ValidationException $exception) {
            return back()->with('error', $this->paymentErrorMessage($exception));
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', 'Nie udalo sie uruchomic platnosci. Sprawdz konfiguracje Przelewy24 i sprobuj ponownie.');
        }
    }

    public function confirmManualPayment(
        Request $request,
        PackageOrder $packageOrder,
        ShootingRangeSubscriptionService $subscriptions
    ): RedirectResponse {
        if ($packageOrder->status === PackageOrder::STATUS_CANCELLED) {
            return back()->with('error', 'Nie mozna rozliczyc usunietego zamowienia abonamentu.');
        }

        if ($packageOrder->payment_status === PackageOrder::PAYMENT_PAID) {
            return back()->with('error', 'To zamowienie abonamentu jest juz rozliczone.');
        }

        $validated = $request->validate([
            'payment_method' => ['required', 'in:cash,bank_transfer,free'],
            'manual_reason' => ['nullable', 'string', 'max:2000'],
            'internal_note' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($packageOrder, $subscriptions, $request, $validated): void {
            $paymentNote = match ($validated['payment_method']) {
                PackageOrder::METHOD_BANK_TRANSFER => $subscriptions->transferTitleFor($packageOrder),
                PackageOrder::METHOD_CASH => 'Rozliczenie gotowka przez superadmina',
                PackageOrder::METHOD_FREE => 'Rozliczenie bezplatne przez superadmina',
            };

            $orderUpdate = [
                'payment_method' => $validated['payment_method'],
                'manual_reason' => $validated['manual_reason'] ?? null,
                'internal_note' => $validated['internal_note'] ?? null,
                'payment_note' => $paymentNote,
            ];

            if ($validated['payment_method'] === PackageOrder::METHOD_FREE) {
                $orderUpdate['amount_gross'] = 0;
            }

            $packageOrder->update($orderUpdate);

            $payment = $packageOrder->payments()->latest()->firstOrFail();
            $paymentUpdate = [
                'provider' => 'manual',
                'method' => $validated['payment_method'],
                'note' => $paymentNote,
            ];

            if ($validated['payment_method'] === PackageOrder::METHOD_FREE) {
                $paymentUpdate['amount'] = 0;
                $paymentUpdate['amount_minor'] = 0;
            }

            $payment->update($paymentUpdate);

            $subscriptions->markOrderPaid(
                $packageOrder->refresh()->loadMissing(['interval', 'shootingRange', 'billingCharges']),
                $payment->refresh(),
                $request->user(),
                $paymentNote
            );
        });

        return back()->with('success', 'Rozliczono platnosc abonamentu.');
    }

    public function changePaymentMethod(
        Request $request,
        PackageOrder $packageOrder,
        ShootingRangeSubscriptionService $subscriptions
    ): RedirectResponse {
        if ($packageOrder->status === PackageOrder::STATUS_CANCELLED) {
            return back()->with('error', 'Nie mozna zmienic formy platnosci usunietego zamowienia abonamentu.');
        }

        if ($packageOrder->payment_status === PackageOrder::PAYMENT_PAID) {
            return back()->with('error', 'Nie mozna zmienic formy platnosci rozliczonego zamowienia abonamentu.');
        }

        $validated = $request->validate([
            'payment_method' => ['required', 'in:bank_transfer,cash'],
            'payment_note' => ['required', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($packageOrder, $subscriptions, $validated): void {
            $paymentNote = $validated['payment_method'] === PackageOrder::METHOD_BANK_TRANSFER
                ? $subscriptions->transferTitleFor($packageOrder)
                : $validated['payment_note'];

            $packageOrder->update([
                'payment_method' => $validated['payment_method'],
                'payment_note' => $paymentNote,
            ]);

            $packageOrder->payments()->latest()->first()?->update([
                'provider' => 'manual',
                'method' => $validated['payment_method'],
                'note' => $paymentNote,
            ]);
        });

        return back()->with('success', 'Zmieniono forme platnosci.');
    }

    public function destroy(
        Request $request,
        PackageOrder $packageOrder,
        ShootingRangeSubscriptionService $subscriptions
    ): RedirectResponse {
        if ($packageOrder->payment_status === PackageOrder::PAYMENT_PAID) {
            return back()->with('error', 'Nie mozna usunac oplaconego zamowienia abonamentu.');
        }

        $subscriptions->cancelUnpaidOrder(
            $packageOrder->loadMissing(['interval', 'payments']),
            $request->user(),
            'Zamowienie abonamentu zostalo usuniete przez superadmina.'
        );

        return back()->with('success', 'Usunieto nieoplacone zamowienie abonamentu.');
    }

    public function return(
        Request $request,
        PackageOrder $packageOrder,
        Przelewy24Service $przelewy24,
        ShootingRangeSubscriptionService $subscriptions
    ): RedirectResponse {
        $payload = $request->all();
        $orderId = (int) ($payload['orderId'] ?? $payload['p24_order_id'] ?? 0);
        $methodId = $payload['methodId'] ?? $payload['p24_method'] ?? null;

        if ($orderId > 0 && $packageOrder->payment_status !== PackageOrder::PAYMENT_PAID) {
            $payment = $packageOrder->payments()
                ->where('provider', 'przelewy24')
                ->latest()
                ->first();

            if ($payment && $przelewy24->verify($payment, $orderId)) {
                $payment->update([
                    'p24_order_id' => $orderId,
                    'p24_method' => $methodId,
                ]);

                $subscriptions->markOrderPaid($packageOrder, $payment);

                return redirect()
                    ->route('shooting.subscription.index')
                    ->with('success', 'Płatność abonamentu została potwierdzona.');
            }
        }

        return redirect()
            ->route('shooting.subscription.index')
            ->with('success', 'Platnosc zostala przekazana do weryfikacji.');
    }

    public function reservationReturn(Request $request, Reservation $reservation): RedirectResponse
    {
        $message = match ($reservation->payment_status) {
            Reservation::PAYMENT_PAID => 'Płatność została potwierdzona. Rezerwacja jest zatwierdzona.',
            Reservation::PAYMENT_EXPIRED => 'Czas na opłacenie rezerwacji wygasł.',
            Reservation::PAYMENT_FAILED => 'Płatność nie została ukończona.',
            default => 'Płatność została przekazana do weryfikacji. Status rezerwacji zostanie zaktualizowany automatycznie.',
        };

        return redirect()
            ->route('rezerwacje.calendar', [
                'id' => $reservation->shooting_range_id,
                'date' => $reservation->start->format('Y-m-d'),
            ])
            ->with(
                $reservation->payment_status === Reservation::PAYMENT_PAID ? 'success' : 'error',
                $message
            );
    }

    public function status(
        Request $request,
        Przelewy24Service $przelewy24,
        ShootingRangeSubscriptionService $subscriptions,
        ReservationPaymentService $reservationPayments
    ) {
        $payload = $request->all();
        $sessionId = $payload['sessionId'] ?? $payload['p24_session_id'] ?? null;
        $orderId = (int) ($payload['orderId'] ?? $payload['p24_order_id'] ?? 0);
        $methodId = $payload['methodId'] ?? $payload['p24_method'] ?? null;

        $payment = Payment::query()
            ->where('p24_session_id', $sessionId)
            ->first();

        $event = PaymentEvent::create([
            'payment_id' => $payment?->id,
            'provider' => 'przelewy24',
            'event_type' => 'status',
            'payload' => $payload,
            'received_at' => now(),
        ]);

        if (! $payment) {
            $event->update([
                'processing_status' => 'failed',
                'error_message' => 'Nie znaleziono platnosci dla sessionId.',
                'processed_at' => now(),
            ]);

            return response('missing payment', 404);
        }

        $reservation = $payment->payable instanceof Reservation
            ? $payment->payable->loadMissing('shootingRange.paymentAccount')
            : null;

        try {
            $verified = $reservation
                ? $przelewy24->verifyForAccount($payment, $reservation->shootingRange->paymentAccount, $orderId)
                : $przelewy24->verify($payment, $orderId);
        } catch (Throwable $exception) {
            report($exception);

            $event->update([
                'processing_status' => 'failed',
                'error_message' => $exception->getMessage(),
                'processed_at' => now(),
            ]);

            return response('verification failed', 422);
        }

        if ($orderId <= 0 || ! $verified) {
            $event->update([
                'processing_status' => 'failed',
                'error_message' => 'Weryfikacja P24 nie powiodla sie.',
                'processed_at' => now(),
            ]);

            return response('verification failed', 422);
        }

        $payment->update([
            'p24_order_id' => $orderId,
            'p24_method' => $methodId,
        ]);

        try {
            if ($reservation) {
                $reservationPayments->markPaid($reservation, $payment);
            } elseif ($payment->packageOrder) {
                $subscriptions->markOrderPaid($payment->packageOrder, $payment);
            } else {
                throw ValidationException::withMessages([
                    'payment' => 'Płatność nie jest powiązana z obsługiwanym dokumentem.',
                ]);
            }
        } catch (ValidationException $exception) {
            $event->update([
                'processing_status' => 'failed',
                'error_message' => $this->paymentErrorMessage($exception),
                'processed_at' => now(),
            ]);

            return response('order not payable', 422);
        } catch (Throwable $exception) {
            report($exception);

            $event->update([
                'processing_status' => 'failed',
                'error_message' => $exception->getMessage(),
                'processed_at' => now(),
            ]);

            return response('processing failed', 500);
        }

        $event->update([
            'processing_status' => 'processed',
            'processed_at' => now(),
        ]);

        return response('OK');
    }

    private function redirectToPayment(PackageOrder $order, Przelewy24Service $przelewy24): RedirectResponse
    {
        $payment = $order->payments()->latest()->firstOrFail();

        if (! app(ShootingRangeSubscriptionService::class)->isOrderPayable($order->loadMissing(['package', 'shootingRange']))) {
            throw ValidationException::withMessages([
                'payment' => 'Tego zamowienia nie mozna juz oplacic, poniewaz pakiet zostal wycofany albo zamowienie nie jest aktywne.',
            ]);
        }

        $payment->update([
            'url_return' => route('subscription-orders.return', $order),
            'url_status' => route('payments.p24.status'),
        ]);

        return redirect()->away($przelewy24->register($payment));
    }

    private function managedShootingRange(Request $request): ?ShootingRange
    {
        return $request->user()
            ->shootingRanges()
            ->wherePivot('is_approved', true)
            ->wherePivot('role', 'admin')
            ->first();
    }

    private function canManageOrder(Request $request, PackageOrder $order): bool
    {
        if ($request->user()?->isSuperAdmin()) {
            return true;
        }

        $shootingRange = $this->managedShootingRange($request);

        return $shootingRange?->id === $order->shooting_range_id;
    }

    private function paymentErrorMessage(ValidationException $exception): string
    {
        return collect($exception->errors())
            ->flatten()
            ->first() ?: 'Nie udalo sie uruchomic platnosci.';
    }
}

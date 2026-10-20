<?php

namespace App\Http\Controllers;

use App\Mail\ReservationPaymentNotificationMail;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\ReservationPricePromotion;
use App\Models\ReservationPriceRule;
use App\Models\ShootingRange;
use App\Models\ShootingRangePaymentAccount;
use App\Services\Przelewy24Service;
use App\Services\ReservationPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ShootingRangeReservationPaymentController extends Controller
{
    public function index(Request $request): View
    {
        $shootingRange = $this->resolveShootingRange($request);
        $paymentAccount = $shootingRange->paymentAccount()->firstOrCreate([
            'shooting_range_id' => $shootingRange->id,
        ]);

        $priceRules = $shootingRange->reservationPriceRules()
            ->with('lane')
            ->orderByDesc('is_active')
            ->orderBy('priority')
            ->orderBy('name')
            ->get();

        $promotions = $shootingRange->reservationPricePromotions()
            ->with('lane')
            ->orderByDesc('is_active')
            ->orderBy('priority')
            ->orderBy('name')
            ->get();

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(array_keys($this->statusLabels()))],
            'method' => ['nullable', Rule::in(array_keys($this->methodLabels()))],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'sort' => ['nullable', Rule::in(['id', 'customer', 'reservation_start', 'status', 'amount', 'method', 'created_at'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);

        $payments = $this->paymentsQuery($shootingRange, $request)
            ->tap(fn ($query) => $this->applyPaymentSorting(
                $query,
                $validated['sort'] ?? 'created_at',
                $validated['direction'] ?? 'desc'
            ))
            ->paginate(20)
            ->withQueryString();

        $managedRanges = $this->managedRanges($request);
        $statusLabels = $this->statusLabels();
        $statusBadgeClasses = $this->statusBadgeClasses();
        $methodLabels = $this->methodLabels();

        return view('admin.reservation-payments.index', compact(
            'shootingRange',
            'paymentAccount',
            'priceRules',
            'promotions',
            'payments',
            'managedRanges',
            'statusLabels',
            'statusBadgeClasses',
            'methodLabels'
        ));
    }

    public function updateAccount(
        Request $request,
        Przelewy24Service $przelewy24
    ): RedirectResponse {
        $shootingRange = $this->resolveShootingRange($request);
        $validated = $request->validate([
            'p24_enabled' => ['nullable', 'boolean'],
            'p24_sandbox' => ['nullable', 'boolean'],
            'p24_merchant_id' => ['nullable', 'string', 'max:50', 'required_if:p24_enabled,1'],
            'p24_pos_id' => ['nullable', 'string', 'max:50', 'required_if:p24_enabled,1'],
            'p24_crc' => ['nullable', 'string', 'max:255', 'required_if:p24_enabled,1'],
            'p24_api_key' => ['nullable', 'string', 'max:255', 'required_if:p24_enabled,1'],
            'currency' => ['required', 'string', 'size:3'],
            'reservation_payment_time_limit_minutes' => ['required', 'integer', 'min:5', 'max:120'],
            'force_save_without_p24' => ['nullable', 'boolean'],
        ]);

        $wantsEnabled = $request->boolean('p24_enabled');
        $hasActivePriceRule = $shootingRange->reservationPriceRules()
            ->where('is_active', true)
            ->where('price_per_position_30min', '>', 0)
            ->exists();

        if ($wantsEnabled && ! $hasActivePriceRule) {
            return back()
                ->withInput()
                ->with('error', 'Przed włączeniem Przelewy24 dodaj przynajmniej jeden aktywny cennik rezerwacji z ceną większą od 0.');
        }

        $testResult = null;

        if ($wantsEnabled) {
            try {
                $testResult = $przelewy24->testAccountAccess($validated);
            } catch (\Throwable $exception) {
                $testResult = [
                    'ok' => false,
                    'message' => $exception->getMessage(),
                ];
            }

            if (! $testResult['ok'] && ! $request->boolean('force_save_without_p24')) {
                return back()
                    ->withInput()
                    ->with('error', 'Dane Przelewy24 są niepoprawne: '.$testResult['message']);
            }
        }

        $paymentAccount = $shootingRange->paymentAccount()->firstOrCreate([
            'shooting_range_id' => $shootingRange->id,
        ]);

        $enabled = $wantsEnabled && (bool) ($testResult['ok'] ?? false);

        $paymentAccount->update([
            'p24_enabled' => $enabled,
            'p24_sandbox' => $request->boolean('p24_sandbox', true),
            'p24_merchant_id' => $validated['p24_merchant_id'] ?? null,
            'p24_pos_id' => $validated['p24_pos_id'] ?? null,
            'p24_crc' => $validated['p24_crc'] ?? null,
            'p24_api_key' => $validated['p24_api_key'] ?? null,
            'currency' => mb_strtoupper($validated['currency']),
            'reservation_payment_time_limit_minutes' => (int) $validated['reservation_payment_time_limit_minutes'],
            'status' => $enabled
                ? ShootingRangePaymentAccount::STATUS_READY
                : ($wantsEnabled ? ShootingRangePaymentAccount::STATUS_INVALID : ShootingRangePaymentAccount::STATUS_NOT_CONFIGURED),
            'last_tested_at' => $testResult ? now() : $paymentAccount->last_tested_at,
            'last_test_success' => $testResult['ok'] ?? $paymentAccount->last_test_success,
            'last_test_message' => $testResult['message'] ?? $paymentAccount->last_test_message,
        ]);

        $message = $enabled
            ? 'Zapisano konfigurację Przelewy24 i włączono płatności online.'
            : 'Zapisano dane Przelewy24, ale płatności online pozostały wyłączone.';

        return back()->with('success', $message);
    }

    public function testAccount(Request $request, Przelewy24Service $przelewy24): JsonResponse
    {
        $this->resolveShootingRange($request);
        $validated = $request->validate([
            'p24_sandbox' => ['nullable', 'boolean'],
            'p24_merchant_id' => ['required', 'string', 'max:50'],
            'p24_pos_id' => ['required', 'string', 'max:50'],
            'p24_crc' => ['required', 'string', 'max:255'],
            'p24_api_key' => ['required', 'string', 'max:255'],
        ]);

        try {
            return response()->json($przelewy24->testAccountAccess($validated));
        } catch (\Throwable $exception) {
            return response()->json([
                'ok' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    public function storePriceRule(Request $request): RedirectResponse
    {
        $shootingRange = $this->resolveShootingRange($request);
        $validated = $this->validatePriceRule($request, $shootingRange);

        $shootingRange->reservationPriceRules()->create($validated);

        return back()->with('success', 'Dodano pozycję cennika rezerwacji.');
    }

    public function updatePriceRule(Request $request, ReservationPriceRule $priceRule): RedirectResponse
    {
        $shootingRange = $this->resolveShootingRange($request);
        abort_if($priceRule->shooting_range_id !== $shootingRange->id, 404);

        $priceRule->update($this->validatePriceRule($request, $shootingRange));

        return back()->with('success', 'Zaktualizowano pozycję cennika rezerwacji.');
    }

    public function destroyPriceRule(Request $request, ReservationPriceRule $priceRule): RedirectResponse
    {
        $shootingRange = $this->resolveShootingRange($request);
        abort_if($priceRule->shooting_range_id !== $shootingRange->id, 404);

        $priceRule->delete();

        return back()->with('success', 'Usunięto pozycję cennika rezerwacji.');
    }

    public function storePromotion(Request $request): RedirectResponse
    {
        $shootingRange = $this->resolveShootingRange($request);
        $validated = $this->validatePromotion($request, $shootingRange);

        $shootingRange->reservationPricePromotions()->create($validated);

        return back()->with('success', 'Dodano promocję rezerwacji.');
    }

    public function updatePromotion(Request $request, ReservationPricePromotion $promotion): RedirectResponse
    {
        $shootingRange = $this->resolveShootingRange($request);
        abort_if($promotion->shooting_range_id !== $shootingRange->id, 404);

        $promotion->update($this->validatePromotion($request, $shootingRange));

        return back()->with('success', 'Zaktualizowano promocję rezerwacji.');
    }

    public function destroyPromotion(Request $request, ReservationPricePromotion $promotion): RedirectResponse
    {
        $shootingRange = $this->resolveShootingRange($request);
        abort_if($promotion->shooting_range_id !== $shootingRange->id, 404);

        $promotion->delete();

        return back()->with('success', 'Usunięto promocję rezerwacji.');
    }

    public function confirmManualPayment(
        Request $request,
        Payment $payment,
        ReservationPaymentService $reservationPayments
    ): RedirectResponse {
        $shootingRange = $this->resolveShootingRange($request);
        $reservation = $payment->payable;

        abort_if(! $reservation instanceof Reservation || $reservation->shooting_range_id !== $shootingRange->id, 404);

        if (! in_array($payment->method, ['bank_transfer', 'cash'], true)) {
            return back()->with('error', 'Ręcznie można rozliczać tylko płatności przelewem tradycyjnym albo gotówką.');
        }

        if (! in_array($payment->status, [
            Payment::STATUS_CREATED,
            Payment::STATUS_REGISTERED,
            Payment::STATUS_PENDING,
        ], true)) {
            return back()->with('error', 'Ta płatność nie oczekuje na potwierdzenie wpłaty.');
        }

        $validated = $request->validate([
            'confirmation_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $payment->update([
            'status' => Payment::STATUS_PAID,
            'confirmed_by_user_id' => $request->user()->id,
            'confirmed_at' => now(),
            'confirmation_note' => $validated['confirmation_note'] ?? null,
        ]);

        $reservation->update([
            'payment_status' => Reservation::PAYMENT_PAID,
            'paid_at' => now(),
        ]);

        $reservationPayments->sendPaymentMail(
            $reservation,
            ReservationPaymentNotificationMail::EVENT_CONFIRMED
        );

        return back()->with('success', 'Ręcznie rozliczono płatność rezerwacji.');
    }

    public function resendPaymentLink(
        Request $request,
        Payment $payment,
        Przelewy24Service $przelewy24,
        ReservationPaymentService $reservationPayments
    ): RedirectResponse {
        $shootingRange = $this->resolveShootingRange($request);
        $reservation = $payment->payable;

        abort_if(! $reservation instanceof Reservation || $reservation->shooting_range_id !== $shootingRange->id, 404);

        if ($payment->method !== 'p24') {
            return back()->with('error', 'Link płatności można wysłać ponownie tylko dla płatności Przelewy24.');
        }

        if ($payment->status === Payment::STATUS_PAID) {
            return back()->with('error', 'Ta płatność jest już opłacona.');
        }

        if (! $reservation->customer_email) {
            return back()->with('error', 'Rezerwacja nie ma adresu e-mail klienta, więc nie można wysłać linku płatności.');
        }

        $paymentAccount = $shootingRange->paymentAccount;

        if (! $paymentAccount?->p24_enabled) {
            return back()->with('error', 'Przelewy24 są wyłączone dla tej strzelnicy.');
        }

        try {
            $paymentUrl = $przelewy24->activePaymentUrlForAccount($payment, $paymentAccount);
            $reservationPayments->sendPaymentMail(
                $reservation,
                ReservationPaymentNotificationMail::EVENT_CREATED,
                $paymentUrl
            );
        } catch (\Throwable $exception) {
            report($exception);

            return back()->with('error', 'Nie udało się ponownie wysłać linku płatności: '.$exception->getMessage());
        }

        return back()->with('success', 'Wysłano ponownie link do płatności.');
    }

    public function confirmManualRefund(
        Request $request,
        Payment $payment
    ): RedirectResponse {
        $shootingRange = $this->resolveShootingRange($request);
        $reservation = $payment->payable;

        abort_if(
            ! $reservation instanceof Reservation
            || $reservation->shooting_range_id !== $shootingRange->id,
            404
        );

        if (! in_array($payment->method, ['bank_transfer', 'cash'], true)) {
            return back()->with('error', 'Ręcznie można potwierdzić zwrot wyłącznie dla przelewu tradycyjnego albo gotówki.');
        }

        if ($payment->status !== Payment::STATUS_REFUND_PENDING) {
            return back()->with('error', 'Ta płatność nie oczekuje na ręczne potwierdzenie zwrotu.');
        }

        $requestedAmountMinor = (int) (
            $payment->refund_requested_amount_minor
            ?: max(0, (int) $payment->amount_minor - (int) $payment->refunded_amount_minor)
        );
        $validated = $request->validate([
            'refunded_amount' => [
                'required',
                'numeric',
                'min:0.01',
                'max:'.number_format($requestedAmountMinor / 100, 2, '.', ''),
            ],
            'refunded_at' => ['required', 'date', 'before_or_equal:now'],
            'confirmation_note' => ['required', 'string', 'max:2000'],
        ]);
        $refundedAmountMinor = (int) round((float) $validated['refunded_amount'] * 100);

        if ($refundedAmountMinor !== $requestedAmountMinor) {
            return back()->withInput()->with(
                'error',
                'Potwierdzana kwota musi odpowiadać kwocie zwrotu: '
                .number_format($requestedAmountMinor / 100, 2, ',', ' ')
                .' '.$payment->currency.'.'
            );
        }

        $refundedTotal = (int) $payment->refunded_amount_minor + $refundedAmountMinor;
        $isFullRefund = $refundedTotal >= (int) $payment->amount_minor;

        $payment->update([
            'status' => $isFullRefund
                ? Payment::STATUS_REFUNDED
                : Payment::STATUS_PARTIALLY_REFUNDED,
            'refunded_amount_minor' => $refundedTotal,
            'refund_requested_amount_minor' => null,
            'refunded_at' => $validated['refunded_at'],
            'confirmed_by_user_id' => $request->user()->id,
            'confirmed_at' => now(),
            'confirmation_note' => $validated['confirmation_note'],
            'refund_error' => null,
        ]);

        if ($isFullRefund) {
            $reservation->update([
                'payment_status' => Reservation::PAYMENT_REFUNDED,
            ]);
        }

        return back()->with(
            'success',
            $isFullRefund
                ? 'Zwrot płatności został rozliczony.'
                : 'Częściowy zwrot został rozliczony.'
        );
    }

    public function export(Request $request)
    {
        $shootingRange = $this->resolveShootingRange($request);
        $payments = $this->paymentsQuery($shootingRange, $request)
            ->latest()
            ->get();

        $rows = [[
            'ID',
            'Status',
            'Metoda',
            'Kwota',
            'Waluta',
            'Klient',
            'Email',
            'Rezerwacja od',
            'Rezerwacja do',
            'Utworzono',
            'Potwierdzono',
        ]];

        foreach ($payments as $payment) {
            $reservation = $payment->payable;
            $rows[] = [
                $payment->id,
                $payment->status,
                $payment->method,
                number_format((float) $payment->amount, 2, '.', ''),
                $payment->currency,
                $reservation?->customer_name ?? $payment->payer_name,
                $reservation?->customer_email ?? $payment->payer_email,
                $reservation?->start?->format('Y-m-d H:i'),
                $reservation?->end?->format('Y-m-d H:i'),
                $payment->created_at?->format('Y-m-d H:i'),
                $payment->confirmed_at?->format('Y-m-d H:i'),
            ];
        }

        $callback = function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            foreach ($rows as $row) {
                fputcsv($handle, $row, ';');
            }
            fclose($handle);
        };

        return Response::streamDownload(
            $callback,
            'platnosci-rezerwacji-'.$shootingRange->id.'-'.now()->format('Ymd_His').'.csv',
            ['Content-Type' => 'text/csv; charset=UTF-8']
        );
    }

    private function paymentsQuery(ShootingRange $shootingRange, Request $request)
    {
        return Payment::query()
            ->with(['payable'])
            ->whereHasMorph(
                'payable',
                [Reservation::class],
                fn ($query) => $query->where('shooting_range_id', $shootingRange->id)
            )
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim((string) $request->query('search'));

                foreach (preg_split('/\s+/u', $search, -1, PREG_SPLIT_NO_EMPTY) as $term) {
                    $query->where(function ($searchQuery) use ($term) {
                        $searchQuery
                            ->where('payments.id', $term)
                            ->orWhere('payments.payer_name', 'like', "%{$term}%")
                            ->orWhere('payments.payer_email', 'like', "%{$term}%")
                            ->orWhere('payments.description', 'like', "%{$term}%")
                            ->orWhere('payments.p24_session_id', 'like', "%{$term}%")
                            ->orWhere('payments.p24_order_id', 'like', "%{$term}%")
                            ->orWhereHasMorph('payable', [Reservation::class], function ($reservationQuery) use ($term) {
                                $reservationQuery
                                    ->where('customer_name', 'like', "%{$term}%")
                                    ->orWhere('customer_email', 'like', "%{$term}%")
                                    ->orWhere('customer_phone', 'like', "%{$term}%");
                            });
                    });
                }
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->query('status')))
            ->when($request->filled('method'), fn ($query) => $query->where('method', $request->query('method')))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('created_at', '>=', $request->query('date_from')))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('created_at', '<=', $request->query('date_to')));
    }

    private function applyPaymentSorting($query, string $sort, string $direction): void
    {
        match ($sort) {
            'customer' => $query
                ->orderBy('payer_name', $direction)
                ->orderBy('payer_email', $direction),
            'reservation_start' => $query
                ->leftJoin('reservations', function ($join) {
                    $join->on('payments.payable_id', '=', 'reservations.id')
                        ->where('payments.payable_type', Reservation::class);
                })
                ->select('payments.*')
                ->orderBy('reservations.start', $direction),
            'status' => $query->orderBy('status', $direction),
            'amount' => $query->orderBy('amount', $direction),
            'method' => $query->orderBy('method', $direction),
            'id' => $query->orderBy('id', $direction),
            default => $query->orderBy('created_at', $direction),
        };

        $query->orderBy('payments.id', $direction);
    }

    private function statusLabels(): array
    {
        return [
            Payment::STATUS_CREATED => 'Utworzona',
            Payment::STATUS_REGISTERED => 'Link wysłany',
            Payment::STATUS_PENDING => 'Oczekuje',
            Payment::STATUS_PAID => 'Opłacona',
            Payment::STATUS_FAILED => 'Nieudana',
            Payment::STATUS_EXPIRED => 'Wygasła',
            Payment::STATUS_REFUND_PENDING => 'Zwrot oczekuje',
            Payment::STATUS_PARTIALLY_REFUNDED => 'Częściowo zwrócona',
            Payment::STATUS_REFUNDED => 'Zwrócona',
            Payment::STATUS_REFUND_FAILED => 'Zwrot nieudany',
        ];
    }

    private function statusBadgeClasses(): array
    {
        return [
            Payment::STATUS_CREATED => 'bg-secondary',
            Payment::STATUS_REGISTERED => 'bg-info text-dark',
            Payment::STATUS_PENDING => 'bg-warning text-dark',
            Payment::STATUS_PAID => 'bg-success',
            Payment::STATUS_FAILED => 'bg-danger',
            Payment::STATUS_EXPIRED => 'bg-dark',
            Payment::STATUS_REFUND_PENDING => 'bg-warning text-dark',
            Payment::STATUS_PARTIALLY_REFUNDED => 'bg-info text-dark',
            Payment::STATUS_REFUNDED => 'bg-primary',
            Payment::STATUS_REFUND_FAILED => 'bg-danger',
        ];
    }

    private function methodLabels(): array
    {
        return [
            'p24' => 'Przelewy24',
            'bank_transfer' => 'Przelew tradycyjny',
            'cash' => 'Gotówka',
        ];
    }

    private function validatePriceRule(Request $request, ShootingRange $shootingRange): array
    {
        $validated = $request->validate([
            'lane_id' => [
                'nullable',
                'integer',
                Rule::exists('lanes', 'id')->where('shooting_range_id', $shootingRange->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'weekdays' => ['nullable', 'array'],
            'weekdays.*' => ['integer', 'between:1,7'],
            'time_from' => ['nullable', 'date_format:H:i'],
            'time_to' => ['nullable', 'date_format:H:i', 'after:time_from'],
            'valid_from' => ['nullable', 'date'],
            'valid_to' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'price_per_position_30min' => ['required', 'numeric', 'min:0.01', 'max:999999'],
            'dynamic_price_per_position_30min' => ['nullable', 'numeric', 'min:0.01', 'max:999999'],
            'currency' => ['required', 'string', 'size:3'],
            'priority' => ['required', 'integer', 'min:1', 'max:999'],
            'is_active' => ['nullable', 'boolean'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $validated['weekdays'] = array_values($validated['weekdays'] ?? []);
        $validated['currency'] = mb_strtoupper($validated['currency']);
        $validated['is_active'] = $request->boolean('is_active');

        return $validated;
    }

    private function validatePromotion(Request $request, ShootingRange $shootingRange): array
    {
        $validated = $request->validate([
            'lane_id' => [
                'nullable',
                'integer',
                Rule::exists('lanes', 'id')->where('shooting_range_id', $shootingRange->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'discount_type' => ['required', Rule::in([
                ReservationPricePromotion::TYPE_PERCENT,
                ReservationPricePromotion::TYPE_AMOUNT,
            ])],
            'discount_value' => ['required', 'numeric', 'min:0.01', 'max:999999'],
            'weekdays' => ['nullable', 'array'],
            'weekdays.*' => ['integer', 'between:1,7'],
            'time_from' => ['nullable', 'date_format:H:i'],
            'time_to' => ['nullable', 'date_format:H:i', 'after:time_from'],
            'valid_from' => ['nullable', 'date'],
            'valid_to' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'priority' => ['required', 'integer', 'min:1', 'max:999'],
            'is_active' => ['nullable', 'boolean'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $validated['weekdays'] = array_values($validated['weekdays'] ?? []);
        $validated['is_active'] = $request->boolean('is_active');

        return $validated;
    }

    private function resolveShootingRange(Request $request): ShootingRange
    {
        $managedRanges = $this->managedRanges($request);
        abort_if($managedRanges->isEmpty(), 404, 'Nie przypisano żadnej strzelnicy.');

        $shootingRangeId = (int) $request->input(
            'shooting_range_id',
            $request->query('shooting_range_id', $managedRanges->first()->id)
        );

        $shootingRange = $managedRanges->firstWhere('id', $shootingRangeId);
        abort_if(! $shootingRange, 403);

        return $shootingRange->loadMissing(['lanes', 'paymentAccount']);
    }

    private function managedRanges(Request $request)
    {
        $user = $request->user();

        if ($user->isSuperAdmin()) {
            return ShootingRange::query()
                ->where('is_archived', false)
                ->orderBy('name')
                ->get();
        }

        return $user->shootingRanges()
            ->wherePivot('is_approved', true)
            ->wherePivot('role', 'admin')
            ->where('is_archived', false)
            ->orderBy('name')
            ->get();
    }
}

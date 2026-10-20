<?php

namespace App\Http\Controllers;

use App\Mail\ReservationChangeRequestMail;
use App\Mail\ReservationNotificationMail;
use App\Mail\ReservationPaymentNotificationMail;
use App\Models\Lane;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\ReservationArchive;
use App\Models\ReservationChangeRequest;
use App\Models\ShootingRange;
use App\Models\User;
use App\Services\Przelewy24Service;
use App\Services\ReservationPaymentService;
use App\Services\ShootingRangeAvailabilityService;
use App\Services\UserSecurityService;
use App\Support\ReservationIntentSession;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ReservationsController extends Controller
{
    public function __construct(
        private readonly ShootingRangeAvailabilityService $availability,
        private readonly ReservationPaymentService $reservationPayments,
        private readonly Przelewy24Service $przelewy24,
        private readonly UserSecurityService $security
    ) {}

    public function callendar(int $id): View|RedirectResponse
    {
        $validated = request()->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $shootingRange = ShootingRange::query()
            ->where('is_visible', true)
            ->where('is_archived', false)
            ->with(['lanes', 'openingHours', 'holidays', 'intervals'])
            ->findOrFail($id);

        $maxAdvanceBookingDays = $shootingRange->effectiveMaxAdvanceBookingDays();
        $maxBookingDate = $shootingRange->latestBookableDate();
        $requestedDate = Carbon::createFromFormat(
            'Y-m-d',
            $validated['date'] ?? now()->format('Y-m-d')
        )->startOfDay();

        if ($requestedDate->lt(today())) {
            $requestedDate = today();
            session()->flash('error', 'Nie można rezerwować terminów z przeszłości.');
        } elseif ($requestedDate->gt($maxBookingDate)) {
            $requestedDate = $maxBookingDate->copy();
            session()->flash(
                'error',
                "Rezerwacji można dokonać maksymalnie {$maxAdvanceBookingDays} dni do przodu."
            );
        }

        $user = auth()->user();
        $canAccessRange = $this->availability->canUserAccessRange(
            $shootingRange,
            $user,
            $requestedDate
        );
        $requiresLoginToReserve = $user === null
            && (bool) $shootingRange->is_public_access;
        $canViewCalendar = $canAccessRange || $requiresLoginToReserve;

        $requirements = $this->availability->basicRequirements(
            $shootingRange,
            $requestedDate
        );

        if (! $requirements['allowed']) {
            session()->flash('error', $requirements['reason']);
        }

        $calendarDate = $requestedDate->format('Y-m-d');
        $reservations = $canViewCalendar
            ? Reservation::query()
                ->where('shooting_range_id', $shootingRange->id)
                ->whereIn('status', [
                    Reservation::STATUS_PENDING,
                    Reservation::STATUS_APPROVED,
                ])
                ->whereDate('start', $requestedDate)
                ->get()
                ->map(fn (Reservation $reservation) => $this->publicReservationData(
                    $reservation
                ))
            : collect();

        return view('reservation.calendar', compact(
            'shootingRange',
            'calendarDate',
            'reservations',
            'maxAdvanceBookingDays',
            'maxBookingDate',
            'canViewCalendar',
            'requiresLoginToReserve'
        ));
    }

    public function availability(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
        ]);
        $date = Carbon::createFromFormat('Y-m-d', $validated['date'])
            ->startOfDay();
        $shootingRange = ShootingRange::query()
            ->where('is_visible', true)
            ->where('is_archived', false)
            ->findOrFail($id);

        if (! $shootingRange->allowsReservationDate($date)) {
            return response()->json([
                'message' => 'Wybrana data znajduje się poza dozwolonym terminem rezerwacji.',
            ], 422);
        }

        $canAccessRange = $this->availability->canUserAccessRange(
            $shootingRange,
            $request->user(),
            $date
        );

        if (
            ! $canAccessRange
            && ! ($request->user() === null && (bool) $shootingRange->is_public_access)
        ) {
            return response()->json([
                'message' => 'Nie posiadasz dostępu do tej strzelnicy.',
            ], 403);
        }

        $requirements = $this->availability->basicRequirements(
            $shootingRange,
            $date
        );

        if (! $requirements['allowed']) {
            return response()->json([
                'message' => $requirements['reason'],
            ], 422);
        }

        $reservations = Reservation::query()
            ->where('shooting_range_id', $shootingRange->id)
            ->whereIn('status', [
                Reservation::STATUS_PENDING,
                Reservation::STATUS_APPROVED,
            ])
            ->whereDate('start', $date)
            ->get()
            ->map(fn (Reservation $reservation) => $this->publicReservationData(
                $reservation
            ))
            ->values();

        return response()->json([
            'reservations' => $reservations,
        ]);
    }

    public function storeIntent(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'shooting_range_id' => ['required', 'integer', 'exists:shooting_ranges,id'],
            'date' => ['required', 'date_format:Y-m-d'],
            'lane_id' => [
                'nullable',
                'integer',
                'exists:lanes,id',
            ],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => [
                'nullable',
                'regex:/^(?:(?:[01]\d|2[0-3]):[0-5]\d|24:00)$/',
            ],
        ]);

        if (
            ! empty($validated['lane_id'])
            && ! Lane::query()
                ->whereKey($validated['lane_id'])
                ->where('shooting_range_id', $validated['shooting_range_id'])
                ->where('is_archived', false)
                ->exists()
        ) {
            throw ValidationException::withMessages([
                'lane_id' => 'Wybrana oś nie należy do wskazanej strzelnicy.',
            ]);
        }

        if (
            ! empty($validated['start_time'])
            && ! empty($validated['end_time'])
            && $this->timeToMinutes($validated['end_time'])
                <= $this->timeToMinutes($validated['start_time'])
        ) {
            throw ValidationException::withMessages([
                'end_time' => 'Godzina zakończenia musi być późniejsza niż godzina rozpoczęcia.',
            ]);
        }

        ReservationIntentSession::store($request, $validated);

        return response()->json([
            'login_url' => route('login'),
            'register_url' => route('register'),
        ]);
    }

    public function quote(Request $request): JsonResponse
    {
        $validated = $this->validateReservationInput($request);
        $actor = $request->user();

        try {
            $shootingRange = ShootingRange::findOrFail($validated['shooting_range_id'])
                ->loadMissing(['paymentAccount', 'reservationPriceRules', 'reservationPricePromotions']);
            $lane = Lane::query()
                ->where('shooting_range_id', $shootingRange->id)
                ->where('is_archived', false)
                ->findOrFail($validated['line_id']);
            [$start, $end] = $this->reservationPeriod($validated);
            $isDynamicShooting = (bool) ($validated['is_dynamic_shooting'] ?? false);
            $positionsCount = $this->requestedPositions(
                $lane,
                (int) $validated['positions_count'],
                $isDynamicShooting
            );

            $this->availability->validateReservation(
                $shootingRange,
                $lane,
                $actor,
                $start,
                $end,
                $positionsCount,
                false,
                $isDynamicShooting
            );

            if (! $shootingRange->paymentAccount?->p24_enabled) {
                return response()->json([
                    'payment_required' => false,
                    'message' => 'Płatność online nie jest wymagana.',
                ]);
            }

            $quote = $this->reservationPayments->quote(
                $shootingRange,
                $lane,
                $start,
                $end,
                $positionsCount,
                $isDynamicShooting
            );

            return response()->json([
                'payment_required' => true,
                'amount' => $quote['amount'],
                'currency' => $quote['currency'],
                'formatted_amount' => number_format((float) $quote['amount'], 2, ',', ' ').' '.$quote['currency'],
                'expires_minutes' => max(5, (int) $shootingRange->paymentAccount->reservation_payment_time_limit_minutes),
                'rule_name' => $quote['rule']->name,
                'promotion_name' => $quote['promotion']?->name,
            ]);
        } catch (ValidationException $exception) {
            return response()->json([
                'message' => collect($exception->errors())->flatten()->first(),
                'errors' => $exception->errors(),
            ], 422);
        } catch (ModelNotFoundException) {
            return response()->json([
                'message' => 'Nie udało się odnaleźć wybranej strzelnicy lub osi.',
            ], 404);
        }
    }

    public function searchCustomers(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'shooting_range_id' => [
                'required',
                'integer',
                'exists:shooting_ranges,id',
            ],
            'q' => ['required', 'email', 'max:255'],
        ]);
        $shootingRange = ShootingRange::findOrFail(
            $validated['shooting_range_id']
        );

        abort_unless(
            $request->user()->managesShootingRange($shootingRange),
            403
        );

        $email = mb_strtolower(trim($validated['q']));
        $users = User::query()
            ->where('is_approved', true)
            ->whereNotNull('email_verified_at')
            ->whereRaw('LOWER(email) = ?', [$email])
            ->orderBy('name')
            ->limit(1)
            ->get([
                'id',
                'name',
                'email',
                'telephone',
                'has_shooting_leader_license',
                'shooting_leader_license_number',
            ]);

        return response()->json([
            'users' => $users,
        ]);
    }

    public function myReservations(Request $request): View
    {
        $user = $request->user();
        $section = $request->query('view') === 'history'
            ? 'history'
            : 'upcoming';
        $reservationOwnershipFilter = function ($query) use ($user): void {
            $query
                ->where('reservations.customer_user_id', $user->id)
                ->orWhere(function ($query) use ($user) {
                    $query
                        ->whereNull('reservations.customer_user_id')
                        ->where('reservations.user_id', $user->id)
                        ->where(function ($query) {
                            $query
                                ->whereNull('reservations.source')
                                ->orWhere(
                                    'reservations.source',
                                    Reservation::SOURCE_ONLINE_ACCOUNT
                                );
                        });
                });
        };
        $archiveOwnershipFilter = function ($query) use ($user): void {
            $query
                ->where('reservation_archives.customer_user_id', $user->id)
                ->orWhere(function ($query) use ($user) {
                    $query
                        ->whereNull('reservation_archives.customer_user_id')
                        ->where('reservation_archives.user_id', $user->id)
                        ->where(function ($query) {
                            $query
                                ->whereNull('reservation_archives.source')
                                ->orWhere(
                                    'reservation_archives.source',
                                    Reservation::SOURCE_ONLINE_ACCOUNT
                                );
                        });
                });
        };

        $upcomingReservations = collect();
        $historyReservations = null;

        if ($section === 'upcoming') {
            $upcomingReservations = Reservation::query()
                ->where($reservationOwnershipFilter)
                ->where('end', '>=', now())
                ->with([
                    'shootingRange:id,name,town',
                    'lane:id,name,length',
                    'pendingChangeRequests',
                ])
                ->orderBy('start')
                ->get();
        } else {
            $currentHistory = DB::table('reservations')
                ->leftJoin(
                    'shooting_ranges',
                    'shooting_ranges.id',
                    '=',
                    'reservations.shooting_range_id'
                )
                ->leftJoin('lanes', 'lanes.id', '=', 'reservations.line_id')
                ->where($reservationOwnershipFilter)
                ->where('reservations.end', '<', now())
                ->select([
                    'reservations.id as original_reservation_id',
                    'reservations.start',
                    'reservations.end',
                    'reservations.positions_count',
                    'reservations.is_dynamic_shooting',
                    'reservations.status',
                    'shooting_ranges.name as shooting_range_name',
                    'shooting_ranges.town as shooting_range_town',
                    'lanes.name as lane_name',
                ])
                ->selectRaw('0 as is_archived');

            $archivedHistory = DB::table('reservation_archives')
                ->where($archiveOwnershipFilter)
                ->select([
                    'original_reservation_id',
                    'start',
                    'end',
                    'positions_count',
                    'is_dynamic_shooting',
                    'status',
                    'shooting_range_name',
                    'shooting_range_town',
                    'lane_name',
                ])
                ->selectRaw('1 as is_archived');

            $historyReservations = DB::query()
                ->fromSub(
                    $currentHistory->unionAll($archivedHistory),
                    'reservation_history'
                )
                ->orderByDesc('start')
                ->paginate(20, ['*'], 'history_page')
                ->withQueryString();
        }

        return view('reservation.mine', compact(
            'section',
            'upcomingReservations',
            'historyReservations'
        ));
    }

    public function editByCustomer(
        Request $request,
        Reservation $reservation
    ): View {
        $this->authorizeCustomerReservation($request, $reservation);
        abort_unless(
            in_array($reservation->status, [
                Reservation::STATUS_PENDING,
                Reservation::STATUS_APPROVED,
            ], true),
            403,
            'Można edytować wyłącznie przyszłą rezerwację oczekującą lub zatwierdzoną.'
        );
        abort_unless(
            $reservation->canBeEditedByCustomer(),
            403,
            'Ta rezerwacja już się rozpoczęła.'
        );
        abort_if(
            $reservation->isPaymentVerificationPending(),
            403,
            'Płatność jest w trakcie weryfikacji. Edycja będzie możliwa po zakończeniu weryfikacji.'
        );
        abort_if(
            $reservation->isPaid() && $reservation->start->diffInHours(now()) < 24,
            403,
            'Opłaconą rezerwację można zmienić najpóźniej 24 godziny przed rozpoczęciem.'
        );
        abort_if(
            $reservation->hasPendingChangeRequest(),
            403,
            'Dla tej rezerwacji istnieje już wniosek oczekujący na decyzję personelu.'
        );

        $shootingRange = $reservation->shootingRange()
            ->with(['lanes', 'openingHours'])
            ->firstOrFail();

        return view('reservation.edit', [
            'reservation' => $reservation,
            'shootingRange' => $shootingRange,
            'openingHours' => $this->openingHoursMap($shootingRange),
            'maxBookingDate' => $shootingRange->latestBookableDate(),
            'paidReservation' => $reservation->isPaid(),
        ]);
    }

    public function updateByCustomer(
        Request $request,
        Reservation $reservation
    ): RedirectResponse {
        $this->authorizeCustomerReservation($request, $reservation);
        $actor = $request->user();
        $payment = null;
        $paymentAccount = null;

        if ($reservation->isPaid()) {
            return $this->requestPaidReservationChange(
                $request,
                $reservation,
                $actor
            );
        }

        $validated = $this->validateReservationInput($request);

        try {
            DB::transaction(function () use (
                $reservation,
                $validated,
                $actor,
                &$payment,
                &$paymentAccount
            ) {
                $lockedReservation = Reservation::query()
                    ->whereKey($reservation->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (
                    ! in_array($lockedReservation->status, [
                        Reservation::STATUS_PENDING,
                        Reservation::STATUS_APPROVED,
                    ], true)
                    || ! $lockedReservation->canBeEditedByCustomer()
                ) {
                    $this->throwReservationError(
                        'Rezerwacja nie może już zostać edytowana.'
                    );
                }

                if ($lockedReservation->isPaymentVerificationPending()) {
                    $this->throwReservationError(
                        'Płatność jest w trakcie weryfikacji. Poczekaj na zakończenie weryfikacji.'
                    );
                }

                if (
                    (int) $validated['shooting_range_id']
                    !== (int) $lockedReservation->shooting_range_id
                ) {
                    $this->throwReservationError(
                        'Nie można przenieść rezerwacji do innej strzelnicy.'
                    );
                }

                $shootingRange = $lockedReservation->shootingRange;
                $lane = Lane::query()
                    ->where('shooting_range_id', $shootingRange->id)
                    ->where('is_archived', false)
                    ->lockForUpdate()
                    ->findOrFail($validated['line_id']);
                [$start, $end] = $this->reservationPeriod($validated);
                $isDynamicShooting = (bool) (
                    $validated['is_dynamic_shooting'] ?? false
                );
                $positionsCount = $this->requestedPositions(
                    $lane,
                    (int) $validated['positions_count'],
                    $isDynamicShooting
                );
                [$hasShootingLeaderLicense, $shootingLeaderLicenseNumber] =
                    $this->shootingLeaderData(
                        $shootingRange,
                        $validated,
                        $actor
                    );

                $this->availability->validateReservation(
                    $shootingRange,
                    $lane,
                    $actor,
                    $start,
                    $end,
                    $positionsCount,
                    false,
                    $isDynamicShooting,
                    $lockedReservation->id
                );

                if (
                    $lockedReservation->status === Reservation::STATUS_APPROVED
                    || $lockedReservation->payment_status === Reservation::PAYMENT_MANUAL
                ) {
                    $changeRequest = $this->createReservationChangeRequest(
                        $lockedReservation,
                        $actor,
                        ReservationChangeRequest::TYPE_CHANGE,
                        [
                            'new_lane_id' => $lane->id,
                            'new_start' => $start,
                            'new_end' => $end,
                            'new_positions_count' => $positionsCount,
                            'new_is_dynamic_shooting' => $isDynamicShooting,
                            'new_has_shooting_leader_license' => $hasShootingLeaderLicense,
                            'new_shooting_leader_license_number' => $shootingLeaderLicenseNumber,
                        ]
                    );

                    $this->notifyReservationChangeRequest(
                        $changeRequest,
                        ReservationChangeRequestMail::EVENT_SUBMITTED,
                        notifyCustomer: false
                    );

                    return;
                }

                $lockedReservation->update([
                    'mod_id' => $actor->id,
                    'line_id' => $lane->id,
                    'start' => $start,
                    'end' => $end,
                    'positions_count' => $positionsCount,
                    'is_dynamic_shooting' => $isDynamicShooting,
                    'has_shooting_leader_license' => $hasShootingLeaderLicense,
                    'shooting_leader_license_number' => $shootingLeaderLicenseNumber,
                    'status' => Reservation::STATUS_PENDING,
                    'approved_by_user_id' => null,
                    'approved_at' => null,
                ]);

                if (
                    $lockedReservation->payment_required
                    && $lockedReservation->payment_status === Reservation::PAYMENT_PENDING
                ) {
                    $paymentAccount = $shootingRange->paymentAccount;
                    $quote = $this->reservationPayments->quote(
                        $shootingRange->loadMissing([
                            'reservationPriceRules',
                            'reservationPricePromotions',
                        ]),
                        $lane,
                        $start,
                        $end,
                        $positionsCount,
                        $isDynamicShooting
                    );
                    $paymentExpiresAt = now()->addMinutes(
                        max(5, (int) $paymentAccount?->reservation_payment_time_limit_minutes)
                    );

                    $lockedReservation->update([
                        'payment_amount' => $quote['amount'],
                        'payment_currency' => $quote['currency'],
                        'payment_expires_at' => $paymentExpiresAt,
                    ]);

                    $lockedReservation->payments()
                        ->whereIn('status', [
                            Payment::STATUS_CREATED,
                            Payment::STATUS_REGISTERED,
                            Payment::STATUS_PENDING,
                        ])
                        ->update([
                            'status' => Payment::STATUS_EXPIRED,
                            'failed_at' => now(),
                        ]);

                    if ($paymentAccount?->p24_enabled) {
                        $payment = $this->reservationPayments->createOnlinePayment(
                            $lockedReservation,
                            $paymentAccount
                        );
                    }
                }
            });
        } catch (ValidationException $exception) {
            return $this->reservationValidationRedirect($exception);
        }

        if ($reservation->fresh()?->hasPendingChangeRequest()) {
            return redirect()
                ->route('rezerwacje.mine')
                ->with('success', 'Wniosek o zmianę rezerwacji został przekazany do zatwierdzenia przez personel strzelnicy.');
        }

        if ($payment && $paymentAccount) {
            try {
                $paymentUrl = $this->przelewy24->registerForAccount(
                    $payment,
                    $paymentAccount
                );
                $this->reservationPayments->sendPaymentMail(
                    $reservation->fresh(),
                    ReservationPaymentNotificationMail::EVENT_CREATED,
                    $paymentUrl
                );

                return redirect()->away($paymentUrl);
            } catch (ValidationException $exception) {
                return $this->reservationValidationRedirect($exception);
            }
        }

        $this->sendReservationNotifications($reservation->fresh(), ReservationNotificationMail::EVENT_UPDATED);
        $this->security->recordIpAddress($actor, $request, 'reservation.updated_by_customer');
        $this->security->audit('reservation.updated_by_customer', $actor, $request, $reservation->fresh(), [
            'shooting_range_id' => $reservation->shooting_range_id,
        ]);

        return redirect()
            ->route('rezerwacje.mine')
            ->with('success', 'Rezerwacja została zaktualizowana.');
    }

    public function cancelByCustomer(
        Request $request,
        Reservation $reservation
    ): RedirectResponse {
        $this->authorizeCustomerReservation($request, $reservation);
        $actor = $request->user();

        $validated = $request->validate([
            'cancellation_reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $message = DB::transaction(function () use (
                $actor,
                $reservation,
                $validated
            ): string {
                $lockedReservation = Reservation::query()
                    ->whereKey($reservation->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! $lockedReservation->canBeEditedByCustomer()) {
                    $this->throwReservationError(
                        'Tej rezerwacji nie można już anulować.'
                    );
                }

                if ($lockedReservation->isPaymentVerificationPending()) {
                    $this->throwReservationError(
                        'Płatność jest w trakcie weryfikacji. Anulowanie będzie możliwe po zakończeniu weryfikacji.'
                    );
                }

                if ($lockedReservation->hasPendingChangeRequest()) {
                    $this->throwReservationError(
                        'Dla tej rezerwacji istnieje już wniosek oczekujący na decyzję personelu.'
                    );
                }

                if (
                    $lockedReservation->isPaid()
                    && $lockedReservation->start->diffInHours(now()) < 24
                ) {
                    $this->throwReservationError(
                        'Opłaconą rezerwację można anulować najpóźniej 24 godziny przed rozpoczęciem.'
                    );
                }

                if (
                    $lockedReservation->isPaid()
                    || $lockedReservation->status === Reservation::STATUS_APPROVED
                    || $lockedReservation->payment_status === Reservation::PAYMENT_MANUAL
                ) {
                    $changeRequest = $this->createReservationChangeRequest(
                        $lockedReservation,
                        $actor,
                        ReservationChangeRequest::TYPE_CANCEL,
                        [
                            'customer_note' => $validated['cancellation_reason'] ?? null,
                        ]
                    );

                    $this->notifyReservationChangeRequest(
                        $changeRequest,
                        ReservationChangeRequestMail::EVENT_SUBMITTED,
                        notifyCustomer: false
                    );

                    return 'Wniosek o anulowanie rezerwacji został przekazany do zatwierdzenia przez personel strzelnicy.';
                }

                $lockedReservation->update([
                    'status' => Reservation::STATUS_CANCELLED,
                    'cancelled_by_user_id' => $actor->id,
                    'cancelled_at' => now(),
                    'cancellation_reason' => $validated['cancellation_reason']
                        ?? 'Anulowano przez klienta.',
                ]);

                $lockedReservation->payments()
                    ->whereIn('status', [
                        Payment::STATUS_CREATED,
                        Payment::STATUS_REGISTERED,
                        Payment::STATUS_PENDING,
                    ])
                    ->update([
                        'status' => Payment::STATUS_EXPIRED,
                        'failed_at' => now(),
                    ]);

                $this->sendReservationNotifications(
                    $lockedReservation,
                    ReservationNotificationMail::EVENT_STATUS_CHANGED
                );

                return 'Rezerwacja została anulowana.';
            });
        } catch (ValidationException $exception) {
            return $this->reservationValidationRedirect($exception);
        }

        $this->security->recordIpAddress($actor, $request, 'reservation.cancelled_by_customer');
        $this->security->audit('reservation.cancelled_by_customer', $actor, $request, $reservation->fresh(), [
            'shooting_range_id' => $reservation->shooting_range_id,
        ]);

        return redirect()
            ->route('rezerwacje.mine')
            ->with('success', $message);
    }

    public function adminCalendar(Request $request): View|RedirectResponse
    {
        $validated = $request->validate([
            'view' => ['nullable', 'in:day,week,month'],
            'date' => ['nullable', 'date'],
            'shooting_range_id' => ['nullable', 'integer'],
        ]);

        $user = $request->user();
        $rangesQuery = $user->isSuperAdmin()
            ? ShootingRange::query()
            : $user->shootingRanges()
                ->wherePivot('is_approved', true)
                ->wherePivotIn('role', ['admin', 'pracownik']);

        $managedRanges = $rangesQuery
            ->orderBy('name')
            ->get([
                'shooting_ranges.id',
                'shooting_ranges.name',
                'shooting_ranges.is_visible',
            ]);

        abort_if($managedRanges->isEmpty(), 404, 'Nie przypisano żadnej strzelnicy.');

        $requestedShootingRangeId = isset($validated['shooting_range_id'])
            ? (int) $validated['shooting_range_id']
            : null;

        if (
            $requestedShootingRangeId !== null
            && ! $managedRanges->contains('id', $requestedShootingRangeId)
        ) {
            abort_if(
                ShootingRange::whereKey($requestedShootingRangeId)->exists(),
                403,
                'Nie masz uprawnień do tej strzelnicy.'
            );

            return redirect()
                ->route('admin.reservations.calendar')
                ->with('error', 'Wybrana strzelnica nie istnieje lub została usunięta.');
        }

        $shootingRangeId = (int) (
            $requestedShootingRangeId
            ?? $managedRanges->first()?->id
        );

        abort_if($shootingRangeId === 0, 404, 'Nie przypisano żadnej strzelnicy.');

        $shootingRange = ShootingRange::with(['lanes', 'openingHours'])
            ->find($shootingRangeId);

        if (! $shootingRange) {
            return redirect()
                ->route('admin.reservations.calendar')
                ->with('error', 'Wybrana strzelnica nie istnieje lub została usunięta.');
        }

        abort_unless(
            $user->managesShootingRange($shootingRange),
            403,
            'Nie masz uprawnień do tej strzelnicy.'
        );

        $reservationNotificationsEnabled = false;

        if (
            ! $user->isSuperAdmin()
            && Schema::hasColumn(
                'shooting_range_employee',
                'receives_reservation_notifications'
            )
        ) {
            $reservationNotificationsEnabled = (bool) $user->shootingRanges()
                ->whereKey($shootingRange->id)
                ->value('shooting_range_employee.receives_reservation_notifications');
        }

        $viewMode = $validated['view'] ?? 'day';
        $selectedDate = Carbon::parse(
            $validated['date'] ?? now()->toDateString()
        )->startOfDay();

        [$periodStart, $periodEnd] = $this->calendarPeriod(
            $viewMode,
            $selectedDate
        );
        $canAdministerRange = $user->administersShootingRange($shootingRange);

        $reservations = Reservation::query()
            ->where('shooting_range_id', $shootingRange->id)
            ->where('start', '<=', $periodEnd)
            ->where('end', '>=', $periodStart)
            ->with([
                'customer:id,name,email,telephone',
                'user:id,name,email,telephone',
                'creator:id,name',
                'approver:id,name',
                'canceller:id,name',
                'finalizer:id,name',
                'lane',
                'payments',
            ])
            ->orderBy('start')
            ->get()
            ->map(function (Reservation $reservation) use ($canAdministerRange, $shootingRange) {
                $payment = $reservation->payments
                    ->sortByDesc('created_at')
                    ->first();

                return [
                    'id' => $reservation->id,
                    'user_name' => $reservation->customer_name
                    ?? $reservation->customer?->name
                    ?? $reservation->user?->name
                    ?? 'Brak danych',
                    'user_email' => $reservation->customer_email
                    ?? $reservation->customer?->email
                    ?? (
                        $reservation->source === Reservation::SOURCE_STAFF_GUEST
                            ? null
                            : $reservation->user?->email
                    ),
                    'user_phone' => $reservation->customer_phone
                    ?? $reservation->customer?->telephone
                    ?? (
                        $reservation->source === Reservation::SOURCE_STAFF_GUEST
                            ? null
                            : $reservation->user?->telephone
                    ),
                    'customer_user_id' => $reservation->customer_user_id,
                    'customer_type' => $reservation->customer_user_id
                        ? 'account'
                        : 'guest',
                    'source' => $reservation->source,
                    'line_id' => $reservation->line_id,
                    'lane_name' => $reservation->lane?->name ?? 'Usunięta oś',
                    'lane_length' => $reservation->lane?->length,
                    'start' => $reservation->start->format('Y-m-d H:i'),
                    'end' => $reservation->end->format('Y-m-d H:i'),
                    'date' => $reservation->start->format('Y-m-d'),
                    'start_time' => $reservation->start->format('H:i'),
                    'end_time' => $this->reservationEndTimeForCalendar($reservation),
                    'positions_count' => $reservation->positions_count,
                    'is_dynamic_shooting' => $reservation->is_dynamic_shooting,
                    'has_shooting_leader_license' => $reservation->has_shooting_leader_license,
                    'shooting_leader_license_number' => $reservation->shooting_leader_license_number,
                    'status' => $reservation->status,
                    'payment_required' => $reservation->payment_required,
                    'payment_status' => $reservation->payment_status,
                    'payment_amount' => $reservation->payment_amount,
                    'payment_currency' => $reservation->payment_currency,
                    'payment_method' => $reservation->payment_method,
                    'payment_expires_at' => $reservation->payment_expires_at?->format('d.m.Y H:i'),
                    'paid_at' => $reservation->paid_at?->format('d.m.Y H:i'),
                    'payment_id' => $payment?->id,
                    'payment_record_status' => $payment?->status,
                    'payment_p24_order_id' => $payment?->p24_order_id,
                    'notes' => $reservation->notes,
                    'cancellation_reason' => $reservation->cancellation_reason,
                    'created_at' => $reservation->created_at?->format('d.m.Y H:i'),
                    'created_by_name' => $reservation->creator?->name,
                    'approved_at' => $reservation->approved_at?->format('d.m.Y H:i'),
                    'approved_by_name' => $reservation->approver?->name,
                    'cancelled_at' => $reservation->cancelled_at?->format('d.m.Y H:i'),
                    'cancelled_by_name' => $reservation->canceller?->name,
                    'finalized_at' => $reservation->finalized_at?->format('d.m.Y H:i'),
                    'finalized_by_name' => $reservation->finalizer?->name,
                    'has_started' => $reservation->start->lessThanOrEqualTo(now()),
                    'has_ended' => $reservation->end->lessThanOrEqualTo(now()),
                    'can_edit' => $shootingRange->is_visible
                    && $reservation->canBeEdited(),
                    'can_delete' => $canAdministerRange
                        && $reservation->canBeHiddenByStaff(),
                    'delete_block_reason' => $reservation->canBeHiddenByStaff()
                        ? null
                        : 'Rezerwację należy najpierw prawidłowo anulować i rozliczyć. Zrealizowanych rezerwacji nie można ukrywać.',
                ];
            })
            ->values();

        $openingHours = $shootingRange->openingHours
            ->mapWithKeys(fn ($openingHour) => [
                (int) $openingHour->day_of_week => [
                    'opens_at' => Carbon::parse($openingHour->opens_at)->format('H:i'),
                    'closes_at' => Carbon::parse($openingHour->closes_at)->format('H:i'),
                ],
            ]);

        $pendingChangeRequests = ReservationChangeRequest::query()
            ->where('status', ReservationChangeRequest::STATUS_PENDING)
            ->whereHas('reservation', fn ($query) => $query->where(
                'shooting_range_id',
                $shootingRange->id
            ))
            ->with([
                'reservation.shootingRange',
                'reservation.lane',
                'requester:id,name,email',
                'oldLane:id,name',
                'newLane:id,name',
            ])
            ->latest()
            ->get();

        return view('admin.reservations.calendar', [
            'shootingRange' => $shootingRange,
            'managedRanges' => $managedRanges,
            'reservations' => $reservations,
            'openingHours' => $openingHours,
            'viewMode' => $viewMode,
            'selectedDate' => $selectedDate->format('Y-m-d'),
            'periodStart' => $periodStart->format('Y-m-d'),
            'periodEnd' => $periodEnd->format('Y-m-d'),
            'maxBookingDate' => $shootingRange->latestBookableDate(),
            'reservationNotificationsEnabled' => $reservationNotificationsEnabled,
            'pendingChangeRequests' => $pendingChangeRequests,
            'canViewHiddenReservations' => $canAdministerRange,
        ]);
    }

    public function hiddenReservations(Request $request): View|RedirectResponse
    {
        $validated = $request->validate([
            'shooting_range_id' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:255'],
        ]);
        $user = $request->user();
        $rangesQuery = $user->isSuperAdmin()
            ? ShootingRange::query()
            : $user->shootingRanges()
                ->wherePivot('is_approved', true)
                ->wherePivot('role', 'admin');
        $managedRanges = $rangesQuery
            ->orderBy('name')
            ->get(['shooting_ranges.id', 'shooting_ranges.name']);

        abort_if(
            ! $user->isSuperAdmin() && $managedRanges->isEmpty(),
            404,
            'Nie przypisano żadnej strzelnicy, którą możesz administrować.'
        );

        $shootingRangeId = isset($validated['shooting_range_id'])
            ? (int) $validated['shooting_range_id']
            : null;

        if ($shootingRangeId !== null) {
            abort_unless(
                $managedRanges->contains('id', $shootingRangeId),
                403,
                'Nie masz uprawnień administracyjnych do tej strzelnicy.'
            );
        }

        $reservations = Reservation::onlyTrashed()
            ->with([
                'shootingRange:id,name,town',
                'lane:id,name,length',
                'deleter:id,name',
                'payments',
            ])
            ->when(
                ! $user->isSuperAdmin(),
                fn ($query) => $query->whereIn(
                    'shooting_range_id',
                    $managedRanges->pluck('id')
                )
            )
            ->when(
                $shootingRangeId !== null,
                fn ($query) => $query->where('shooting_range_id', $shootingRangeId)
            )
            ->when(
                filled($validated['q'] ?? null),
                function ($query) use ($validated): void {
                    $term = trim($validated['q']);
                    $query->where(function ($query) use ($term): void {
                        $like = '%'.$term.'%';
                        $query->where('customer_name', 'like', $like)
                            ->orWhere('customer_email', 'like', $like)
                            ->orWhere('customer_phone', 'like', $like);

                        if (ctype_digit($term)) {
                            $query->orWhereKey((int) $term);
                        }
                    });
                }
            )
            ->orderByDesc('deleted_at')
            ->paginate(25)
            ->withQueryString();

        return view('admin.reservations.hidden', [
            'reservations' => $reservations,
            'managedRanges' => $managedRanges,
            'filters' => $validated,
        ]);
    }

    public function restoreHiddenReservation(
        Request $request,
        int $reservation
    ): RedirectResponse {
        $hiddenReservation = Reservation::onlyTrashed()
            ->with('shootingRange')
            ->findOrFail($reservation);

        abort_unless(
            $request->user()->administersShootingRange($hiddenReservation->shootingRange),
            403
        );

        DB::transaction(function () use ($hiddenReservation): void {
            $lockedReservation = Reservation::onlyTrashed()
                ->whereKey($hiddenReservation->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedReservation->restore();
            $lockedReservation->update([
                'deleted_by_user_id' => null,
                'deletion_reason' => null,
            ]);
        });

        $restoredReservation = $hiddenReservation->fresh();
        $this->security->recordIpAddress(
            $request->user(),
            $request,
            'reservation.restored_by_admin'
        );
        $this->security->audit(
            'reservation.restored_by_admin',
            $request->user(),
            $request,
            $restoredReservation,
            ['shooting_range_id' => $restoredReservation?->shooting_range_id]
        );

        return back()->with('success', 'Rezerwacja została przywrócona jako wpis anulowany i ponownie jest widoczna dla administratorów.');
    }

    public function adminArchive(Request $request): View|RedirectResponse
    {
        $validated = $request->validate([
            'shooting_range_id' => [
                'nullable',
                'integer',
            ],
            'date_from' => [
                'nullable',
                'date',
                'after_or_equal:1900-01-01',
                'before_or_equal:today',
            ],
            'date_to' => [
                'nullable',
                'date',
                'after_or_equal:date_from',
                'before_or_equal:today',
            ],
            'status' => [
                'nullable',
                Rule::in([
                    Reservation::STATUS_CANCELLED,
                    Reservation::STATUS_COMPLETED,
                    Reservation::STATUS_NO_SHOW,
                ]),
            ],
            'q' => ['nullable', 'string', 'max:255'],
        ]);

        $user = $request->user();
        $rangesQuery = $user->isSuperAdmin()
            ? ShootingRange::query()
            : $user->shootingRanges()
                ->wherePivot('is_approved', true)
                ->wherePivotIn('role', ['admin', 'pracownik']);

        $managedRanges = $rangesQuery
            ->orderBy('name')
            ->get(['shooting_ranges.id', 'shooting_ranges.name']);

        abort_if(
            ! $user->isSuperAdmin() && $managedRanges->isEmpty(),
            404,
            'Nie przypisano żadnej strzelnicy.'
        );

        $shootingRangeId = isset($validated['shooting_range_id'])
            ? (int) $validated['shooting_range_id']
            : null;

        if ($shootingRangeId !== null) {
            if (
                ! $managedRanges->contains('id', $shootingRangeId)
                && ! ShootingRange::whereKey($shootingRangeId)->exists()
            ) {
                return redirect()
                    ->route('admin.reservations.archive')
                    ->with('error', 'Wybrana strzelnica nie istnieje lub została usunięta.');
            }

            abort_unless(
                $managedRanges->contains('id', $shootingRangeId),
                403,
                'Nie masz uprawnień do tej strzelnicy.'
            );
        }

        $archives = ReservationArchive::query()
            ->when(
                ! $user->isSuperAdmin(),
                fn ($query) => $query->whereIn(
                    'shooting_range_id',
                    $managedRanges->pluck('id')
                )
            )
            ->when(
                $shootingRangeId !== null,
                fn ($query) => $query->where(
                    'shooting_range_id',
                    $shootingRangeId
                )
            )
            ->when(
                $validated['date_from'] ?? null,
                fn ($query, $date) => $query->whereDate(
                    'start',
                    '>=',
                    $date
                )
            )
            ->when(
                $validated['date_to'] ?? null,
                fn ($query, $date) => $query->whereDate(
                    'start',
                    '<=',
                    $date
                )
            )
            ->when(
                $validated['status'] ?? null,
                fn ($query, $status) => $query->where('status', $status)
            )
            ->when(
                filled($validated['q'] ?? null),
                function ($query) use ($validated): void {
                    $term = trim($validated['q']);
                    $query->where(function ($query) use ($term): void {
                        $like = '%'.$term.'%';
                        $query
                            ->where('customer_name', 'like', $like)
                            ->orWhere('customer_email', 'like', $like)
                            ->orWhere('customer_phone', 'like', $like);

                        if (ctype_digit($term)) {
                            $query->orWhere(
                                'original_reservation_id',
                                (int) $term
                            );
                        }
                    });
                }
            )
            ->orderByDesc('start')
            ->paginate(25)
            ->withQueryString();

        return view('admin.reservations.archive', [
            'archives' => $archives,
            'managedRanges' => $managedRanges,
            'filters' => $validated,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateReservationInput($request);
        $actor = $request->user();
        $payment = null;
        $paymentAccount = null;

        try {
            $reservation = DB::transaction(function () use ($validated, $actor, &$payment, &$paymentAccount): Reservation {
                $shootingRange = ShootingRange::findOrFail(
                    $validated['shooting_range_id']
                )->loadMissing(['paymentAccount', 'reservationPriceRules', 'reservationPricePromotions']);
                $lane = Lane::query()
                    ->where('shooting_range_id', $shootingRange->id)
                    ->where('is_archived', false)
                    ->lockForUpdate()
                    ->findOrFail($validated['line_id']);
                [$start, $end] = $this->reservationPeriod($validated);
                $isDynamicShooting = (bool) (
                    $validated['is_dynamic_shooting'] ?? false
                );
                $positionsCount = $this->requestedPositions(
                    $lane,
                    (int) $validated['positions_count'],
                    $isDynamicShooting
                );
                [$hasShootingLeaderLicense, $shootingLeaderLicenseNumber] =
                    $this->shootingLeaderData(
                        $shootingRange,
                        $validated,
                        $actor
                    );

                $this->availability->validateReservation(
                    $shootingRange,
                    $lane,
                    $actor,
                    $start,
                    $end,
                    $positionsCount,
                    false,
                    $isDynamicShooting
                );

                $paymentAccount = $shootingRange->paymentAccount;
                $requiresOnlinePayment = (bool) $paymentAccount?->p24_enabled;
                $quote = null;

                if ($requiresOnlinePayment) {
                    $quote = $this->reservationPayments->quote(
                        $shootingRange,
                        $lane,
                        $start,
                        $end,
                        $positionsCount,
                        $isDynamicShooting
                    );
                }

                $requiresConfirmation = ! $requiresOnlinePayment
                    && (bool) $shootingRange->requires_reservation_confirmation;
                $status = $requiresOnlinePayment
                    ? Reservation::STATUS_PENDING
                    : ($requiresConfirmation ? Reservation::STATUS_PENDING : Reservation::STATUS_APPROVED);
                $paymentExpiresAt = $requiresOnlinePayment
                    ? now()->addMinutes(max(5, (int) $paymentAccount->reservation_payment_time_limit_minutes))
                    : null;

                $reservation = Reservation::create([
                    'user_id' => $actor->id,
                    'mod_id' => $actor->id,
                    'customer_user_id' => $actor->id,
                    'customer_name' => $actor->name,
                    'customer_email' => $actor->email,
                    'customer_phone' => $actor->telephone,
                    'created_by_user_id' => $actor->id,
                    'source' => Reservation::SOURCE_ONLINE_ACCOUNT,
                    'shooting_range_id' => $shootingRange->id,
                    'line_id' => $lane->id,
                    'start' => $start,
                    'end' => $end,
                    'positions_count' => $positionsCount,
                    'is_dynamic_shooting' => $isDynamicShooting,
                    'has_shooting_leader_license' => $hasShootingLeaderLicense,
                    'shooting_leader_license_number' => $shootingLeaderLicenseNumber,
                    'status' => $status,
                    'payment_required' => $requiresOnlinePayment,
                    'payment_status' => $requiresOnlinePayment
                        ? Reservation::PAYMENT_PENDING
                        : Reservation::PAYMENT_NOT_REQUIRED,
                    'payment_amount' => $quote['amount'] ?? null,
                    'payment_currency' => $quote['currency'] ?? 'PLN',
                    'payment_expires_at' => $paymentExpiresAt,
                    'payment_method' => $requiresOnlinePayment ? 'p24' : null,
                    'approved_at' => $status === Reservation::STATUS_APPROVED ? now() : null,
                ]);

                if ($requiresOnlinePayment) {
                    $payment = $this->reservationPayments->createOnlinePayment($reservation, $paymentAccount);
                }

                return $reservation;
            });
        } catch (ValidationException $exception) {
            return $this->reservationValidationRedirect($exception);
        }

        if ($payment && $paymentAccount) {
            try {
                $paymentUrl = $this->przelewy24->registerForAccount($payment, $paymentAccount);
                $this->reservationPayments->sendPaymentMail(
                    $reservation,
                    ReservationPaymentNotificationMail::EVENT_CREATED,
                    $paymentUrl
                );

                return redirect()->away($paymentUrl);
            } catch (ValidationException $exception) {
                return $this->reservationValidationRedirect($exception);
            }
        }

        $this->sendReservationNotifications(
            $reservation,
            ReservationNotificationMail::EVENT_CREATED
        );
        $this->security->recordIpAddress($actor, $request, 'reservation.created');
        $this->security->audit('reservation.created', $actor, $request, $reservation, [
            'source' => $reservation->source,
            'status' => $reservation->status,
            'shooting_range_id' => $reservation->shooting_range_id,
        ]);

        $message = $reservation->isApproved()
            ? 'Dziękujemy za złożenie rezerwacji. Termin został automatycznie zatwierdzony.'
            : 'Dziękujemy za złożenie rezerwacji. Termin oczekuje na zatwierdzenie przez obsługę strzelnicy.';

        return redirect()
            ->route('rezerwacje.calendar', [
                'id' => $reservation->shooting_range_id,
                'date' => $reservation->start->format('Y-m-d'),
            ])
            ->with('success', $message);
    }

    public function storeByStaff(Request $request): RedirectResponse
    {
        $actor = $request->user();
        $payment = null;
        $paymentAccount = null;
        $paymentUrl = null;
        $staffPaymentMethod = 'none';

        try {
            $validated = $this->validateStaffReservationInput($request);
            $staffPaymentMethod = $validated['payment_method'] ?? 'none';

            if (
                $staffPaymentMethod === 'payment_link'
                && blank($validated['customer_email'] ?? null)
                && blank($validated['customer_user_id'] ?? null)
            ) {
                throw ValidationException::withMessages([
                    'customer_email' => 'Do wysłania linku płatności online wymagany jest adres e-mail klienta.',
                ]);
            }

            $reservation = DB::transaction(function () use ($validated, $actor, $staffPaymentMethod, &$payment, &$paymentAccount): Reservation {
                $shootingRange = ShootingRange::findOrFail(
                    $validated['shooting_range_id']
                )->loadMissing(['paymentAccount', 'reservationPriceRules', 'reservationPricePromotions']);
                $lane = Lane::query()
                    ->where('shooting_range_id', $shootingRange->id)
                    ->where('is_archived', false)
                    ->lockForUpdate()
                    ->findOrFail($validated['line_id']);
                [$start, $end] = $this->reservationPeriod($validated);
                $isDynamicShooting = (bool) (
                    $validated['is_dynamic_shooting'] ?? false
                );
                $positionsCount = $this->requestedPositions(
                    $lane,
                    (int) $validated['positions_count'],
                    $isDynamicShooting
                );

                $this->availability->validateReservation(
                    $shootingRange,
                    $lane,
                    $actor,
                    $start,
                    $end,
                    $positionsCount,
                    true,
                    $isDynamicShooting
                );

                $customer = $validated['customer_type'] === 'account'
                    ? User::query()
                        ->where('is_approved', true)
                        ->findOrFail($validated['customer_user_id'])
                    : null;
                $isGuest = $customer === null;
                [$hasShootingLeaderLicense, $shootingLeaderLicenseNumber] =
                    $this->shootingLeaderData(
                        $shootingRange,
                        $validated,
                        $customer
                    );

                $quote = null;
                $requiresPayment = in_array($staffPaymentMethod, ['cash', 'bank_transfer', 'payment_link'], true);
                $paymentAccount = $shootingRange->paymentAccount;

                if ($requiresPayment) {
                    $quote = $this->reservationPayments->quote(
                        $shootingRange,
                        $lane,
                        $start,
                        $end,
                        $positionsCount,
                        $isDynamicShooting
                    );
                }

                if ($staffPaymentMethod === 'payment_link' && ! $paymentAccount?->p24_enabled) {
                    $this->throwReservationError('Nie można wygenerować linku płatności, ponieważ Przelewy24 są wyłączone dla tej strzelnicy.');
                }

                $paymentExpiresAt = $staffPaymentMethod === 'payment_link'
                    ? now()->addMinutes(max(5, (int) $paymentAccount->reservation_payment_time_limit_minutes))
                    : null;

                $reservation = Reservation::create([
                    // Kolumny zgodnościowe pozostają do czasu drugiego etapu migracji.
                    'user_id' => $customer?->id ?? $actor->id,
                    'mod_id' => $actor->id,
                    'customer_user_id' => $customer?->id,
                    'customer_name' => $customer?->name
                        ?? ($validated['customer_name'] ?? 'Brak danych'),
                    'customer_email' => $customer?->email
                        ?? ($validated['customer_email'] ?? null),
                    'customer_phone' => $customer?->telephone
                        ?? ($validated['customer_phone'] ?? null),
                    'created_by_user_id' => $actor->id,
                    'source' => $isGuest
                        ? Reservation::SOURCE_STAFF_GUEST
                        : Reservation::SOURCE_STAFF_ACCOUNT,
                    'shooting_range_id' => $shootingRange->id,
                    'line_id' => $lane->id,
                    'start' => $start,
                    'end' => $end,
                    'positions_count' => $positionsCount,
                    'is_dynamic_shooting' => $isDynamicShooting,
                    'has_shooting_leader_license' => $hasShootingLeaderLicense,
                    'shooting_leader_license_number' => $shootingLeaderLicenseNumber,
                    'status' => Reservation::STATUS_APPROVED,
                    'payment_required' => $requiresPayment,
                    'payment_status' => match ($staffPaymentMethod) {
                        'cash', 'bank_transfer' => Reservation::PAYMENT_MANUAL,
                        'payment_link' => Reservation::PAYMENT_PENDING,
                        default => Reservation::PAYMENT_NOT_REQUIRED,
                    },
                    'payment_amount' => $quote['amount'] ?? null,
                    'payment_currency' => $quote['currency'] ?? 'PLN',
                    'payment_expires_at' => $paymentExpiresAt,
                    'payment_method' => match ($staffPaymentMethod) {
                        'cash' => 'cash',
                        'bank_transfer' => 'bank_transfer',
                        'payment_link' => 'p24',
                        default => null,
                    },
                    'approved_by_user_id' => $actor->id,
                    'approved_at' => now(),
                    'notes' => $validated['notes'] ?? null,
                ]);

                if (in_array($staffPaymentMethod, ['cash', 'bank_transfer'], true)) {
                    $payment = $reservation->payments()->create([
                        'provider' => 'manual',
                        'method' => $staffPaymentMethod === 'cash' ? 'cash' : 'bank_transfer',
                        'status' => Payment::STATUS_PENDING,
                        'amount' => $reservation->payment_amount,
                        'amount_minor' => (int) round((float) $reservation->payment_amount * 100),
                        'currency' => $reservation->payment_currency,
                        'description' => 'Rezerwacja '.$shootingRange->name.' '.$reservation->start->format('Y-m-d H:i'),
                        'payer_email' => $reservation->customer_email,
                        'payer_name' => $reservation->customer_name,
                    ]);
                }

                if ($staffPaymentMethod === 'payment_link') {
                    $payment = $this->reservationPayments->createOnlinePayment($reservation, $paymentAccount);
                }

                return $reservation;
            });
        } catch (ValidationException $exception) {
            return $this->reservationValidationRedirect($exception, 'create');
        } catch (ModelNotFoundException) {
            return $this->staffReservationErrorRedirect(
                'Nie udało się odnaleźć wybranej strzelnicy, osi lub użytkownika. Odśwież kalendarz i spróbuj ponownie.',
                'create'
            );
        }

        if ($staffPaymentMethod === 'payment_link' && $payment && $paymentAccount) {
            try {
                $paymentUrl = $this->przelewy24->registerForAccount($payment, $paymentAccount);
                $this->reservationPayments->sendPaymentMail(
                    $reservation,
                    ReservationPaymentNotificationMail::EVENT_CREATED,
                    $paymentUrl
                );
            } catch (ValidationException $exception) {
                return $this->reservationValidationRedirect($exception, 'create');
            }
        }

        $this->sendReservationNotifications(
            $reservation,
            ReservationNotificationMail::EVENT_CREATED
        );
        $this->security->recordIpAddress($actor, $request, 'reservation.created_by_staff');
        $this->security->audit('reservation.created_by_staff', $actor, $request, $reservation, [
            'source' => $reservation->source,
            'status' => $reservation->status,
            'shooting_range_id' => $reservation->shooting_range_id,
        ]);

        return back()->with('success', 'Rezerwacja została zapisana i zatwierdzona.');
    }

    public function updateByStaff(
        Request $request,
        Reservation $reservation
    ): RedirectResponse {
        abort_unless(
            $request->user()->managesShootingRange($reservation->shootingRange),
            403
        );
        $actor = $request->user();
        $pricingMessage = null;

        try {
            $validated = $this->validateStaffReservationInput($request);

            DB::transaction(function () use (
                $reservation,
                $validated,
                $actor,
                &$pricingMessage
            ) {
                $lockedReservation = Reservation::query()
                    ->whereKey($reservation->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! $lockedReservation->canBeEdited()) {
                    $this->throwReservationError(
                        'Po zakończeniu terminu nie można edytować parametrów rezerwacji.'
                    );
                }

                if (
                    (int) $validated['shooting_range_id']
                    !== (int) $lockedReservation->shooting_range_id
                ) {
                    $this->throwReservationError(
                        'Nie można przenieść rezerwacji do innej strzelnicy.'
                    );
                }

                $shootingRange = $lockedReservation->shootingRange;
                abort_unless(
                    $actor->managesShootingRange($shootingRange),
                    403
                );
                $lane = Lane::query()
                    ->where('shooting_range_id', $shootingRange->id)
                    ->where('is_archived', false)
                    ->lockForUpdate()
                    ->findOrFail($validated['line_id']);
                [$start, $end] = $this->reservationPeriod($validated);
                $isDynamicShooting = (bool) (
                    $validated['is_dynamic_shooting'] ?? false
                );
                $positionsCount = $this->requestedPositions(
                    $lane,
                    (int) $validated['positions_count'],
                    $isDynamicShooting
                );

                $this->availability->validateReservation(
                    $shootingRange,
                    $lane,
                    $actor,
                    $start,
                    $end,
                    $positionsCount,
                    true,
                    $isDynamicShooting,
                    $lockedReservation->id
                );

                $customer = $validated['customer_type'] === 'account'
                    ? User::query()
                        ->where('is_approved', true)
                        ->findOrFail($validated['customer_user_id'])
                    : null;
                $isGuest = $customer === null;
                [$hasShootingLeaderLicense, $shootingLeaderLicenseNumber] =
                    $this->shootingLeaderData(
                        $shootingRange,
                        $validated,
                        $customer
                    );

                $pricingChanged = (int) $lockedReservation->line_id !== (int) $lane->id
                    || ! $lockedReservation->start->equalTo($start)
                    || ! $lockedReservation->end->equalTo($end)
                    || (int) $lockedReservation->positions_count !== $positionsCount
                    || (bool) $lockedReservation->is_dynamic_shooting !== $isDynamicShooting;

                if ($lockedReservation->isPaid() && $pricingChanged) {
                    $quote = $this->reservationPayments->quote(
                        $shootingRange->loadMissing([
                            'reservationPriceRules',
                            'reservationPricePromotions',
                            'paymentAccount',
                        ]),
                        $lane,
                        $start,
                        $end,
                        $positionsCount,
                        $isDynamicShooting
                    );
                    $oldAmountMinor = (int) round((float) $lockedReservation->payment_amount * 100);
                    $newAmountMinor = (int) $quote['amount_minor'];

                    if ($newAmountMinor > $oldAmountMinor) {
                        $this->throwReservationError(
                            'Zmiana podnosi cenę rezerwacji z '
                            .number_format($oldAmountMinor / 100, 2, ',', ' ')
                            .' zł do '.number_format($newAmountMinor / 100, 2, ',', ' ')
                            .' zł. Najpierw anuluj rezerwację i utwórz nową albo pozostaw parametry bez wpływu na cenę.'
                        );
                    }

                    if ($newAmountMinor < $oldAmountMinor) {
                        $refundAmount = $oldAmountMinor - $newAmountMinor;
                        $paidPayment = $lockedReservation->payments()
                            ->whereIn('status', [
                                Payment::STATUS_PAID,
                                Payment::STATUS_PARTIALLY_REFUNDED,
                            ])
                            ->latest()
                            ->first();

                        if (! $paidPayment) {
                            $this->throwReservationError(
                                'Nie znaleziono rozliczonej płatności wymaganej do zwrotu różnicy.'
                            );
                        }

                        if ($paidPayment->method === 'p24') {
                            $paymentAccount = $shootingRange->paymentAccount;

                            if (! $paymentAccount || ! $this->przelewy24->refundForAccount(
                                $paidPayment,
                                $paymentAccount,
                                $refundAmount,
                                'Korekta rezerwacji #'.$lockedReservation->id
                            )) {
                                $this->throwReservationError(
                                    'Nie udało się zlecić zwrotu różnicy w Przelewy24. Rezerwacja nie została zmieniona.'
                                );
                            }

                            $pricingMessage = 'Zlecono częściowy zwrot '.number_format($refundAmount / 100, 2, ',', ' ').' zł.';
                        } else {
                            $paidPayment->update([
                                'status' => Payment::STATUS_REFUND_PENDING,
                                'refund_requested_amount_minor' => $refundAmount,
                                'refund_error' => 'Do ręcznego zwrotu po zmianie rezerwacji: '.number_format($refundAmount / 100, 2, ',', ' ').' '.$quote['currency'].'.',
                            ]);
                            $pricingMessage = 'Różnica '.number_format($refundAmount / 100, 2, ',', ' ').' zł oczekuje na ręczny zwrot.';
                        }

                        $lockedReservation->payment_amount = $quote['amount'];
                        $lockedReservation->payment_currency = $quote['currency'];
                    }
                }

                $lockedReservation->update([
                    'user_id' => $customer?->id ?? $actor->id,
                    'mod_id' => $actor->id,
                    'customer_user_id' => $customer?->id,
                    'customer_name' => $customer?->name
                        ?? ($validated['customer_name'] ?? 'Brak danych'),
                    'customer_email' => $customer?->email
                        ?? ($validated['customer_email'] ?? null),
                    'customer_phone' => $customer?->telephone
                        ?? ($validated['customer_phone'] ?? null),
                    'source' => $isGuest
                        ? Reservation::SOURCE_STAFF_GUEST
                        : Reservation::SOURCE_STAFF_ACCOUNT,
                    'line_id' => $lane->id,
                    'start' => $start,
                    'end' => $end,
                    'positions_count' => $positionsCount,
                    'is_dynamic_shooting' => $isDynamicShooting,
                    'has_shooting_leader_license' => $hasShootingLeaderLicense,
                    'shooting_leader_license_number' => $shootingLeaderLicenseNumber,
                    'notes' => $validated['notes'] ?? null,
                ]);
            });
        } catch (ValidationException $exception) {
            return $this->reservationValidationRedirect(
                $exception,
                'edit',
                $reservation->id
            );
        } catch (ModelNotFoundException) {
            return $this->staffReservationErrorRedirect(
                'Nie udało się odnaleźć edytowanej rezerwacji, osi lub użytkownika. Odśwież kalendarz i spróbuj ponownie.',
                'edit',
                $reservation->id
            );
        }

        $this->sendReservationNotifications(
            $reservation->fresh(),
            ReservationNotificationMail::EVENT_UPDATED
        );
        $this->security->recordIpAddress($actor, $request, 'reservation.updated_by_staff');
        $this->security->audit('reservation.updated_by_staff', $actor, $request, $reservation->fresh(), [
            'shooting_range_id' => $reservation->shooting_range_id,
        ]);

        return back()->with(
            'success',
            'Rezerwacja została zaktualizowana.'.($pricingMessage ? ' '.$pricingMessage : '')
        );
    }

    public function updateStatus(
        Request $request,
        Reservation $reservation
    ): RedirectResponse {
        abort_unless(
            $request->user()->managesShootingRange($reservation->shootingRange),
            403
        );

        $validated = $request->validate([
            'status' => ['required', Rule::in([
                Reservation::STATUS_PENDING,
                Reservation::STATUS_APPROVED,
                Reservation::STATUS_CANCELLED,
                Reservation::STATUS_COMPLETED,
                Reservation::STATUS_NO_SHOW,
            ])],
            'cancellation_reason' => [
                'required_if:status,'.Reservation::STATUS_CANCELLED,
                'nullable',
                'string',
                'max:500',
            ],
        ]);

        $allowedTransitions = [
            Reservation::STATUS_PENDING => [
                Reservation::STATUS_APPROVED,
                Reservation::STATUS_CANCELLED,
                Reservation::STATUS_COMPLETED,
                Reservation::STATUS_NO_SHOW,
            ],
            Reservation::STATUS_APPROVED => [
                Reservation::STATUS_CANCELLED,
                Reservation::STATUS_COMPLETED,
                Reservation::STATUS_NO_SHOW,
            ],
            Reservation::STATUS_CANCELLED => [
                Reservation::STATUS_PENDING,
                Reservation::STATUS_APPROVED,
            ],
            Reservation::STATUS_COMPLETED => [],
            Reservation::STATUS_NO_SHOW => [],
        ];

        $statusChanged = false;
        $refundResult = [
            'failed' => false,
            'message' => null,
        ];

        try {
            DB::transaction(function () use (
                $allowedTransitions,
                &$statusChanged,
                $request,
                $reservation,
                $validated
            ) {
                $lockedReservation = Reservation::query()
                    ->whereKey($reservation->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($validated['status'] === $lockedReservation->status) {
                    return;
                }

                if (! in_array(
                    $validated['status'],
                    $allowedTransitions[$lockedReservation->status] ?? [],
                    true
                )) {
                    $this->throwReservationError(
                        'Ta zmiana statusu rezerwacji jest niedozwolona.'
                    );
                }

                if (
                    in_array($lockedReservation->status, [
                        Reservation::STATUS_CANCELLED,
                        Reservation::STATUS_PAYMENT_EXPIRED,
                    ], true)
                    && in_array($validated['status'], [
                        Reservation::STATUS_PENDING,
                        Reservation::STATUS_APPROVED,
                    ], true)
                    && in_array($lockedReservation->payment_status, [
                        Reservation::PAYMENT_PAID,
                        Reservation::PAYMENT_REFUNDED,
                    ], true)
                ) {
                    $this->throwReservationError(
                        'Nie można ponownie aktywować anulowanej rezerwacji z rozliczoną płatnością.'
                    );
                }

                $lockedIsFinalStatus = in_array($validated['status'], [
                    Reservation::STATUS_COMPLETED,
                    Reservation::STATUS_NO_SHOW,
                ], true);

                if ($lockedIsFinalStatus && $lockedReservation->end->isFuture()) {
                    $this->throwReservationError(
                        'Status realizacji można ustawić dopiero po zakończeniu rezerwacji.'
                    );
                }

                if (! $lockedIsFinalStatus && ! $lockedReservation->start->isFuture()) {
                    $this->throwReservationError(
                        'Po rozpoczęciu rezerwacji można ustawić wyłącznie status realizacji.'
                    );
                }

                if (in_array($validated['status'], [
                    Reservation::STATUS_PENDING,
                    Reservation::STATUS_APPROVED,
                ], true)) {
                    $lane = Lane::query()
                        ->where('is_archived', false)
                        ->lockForUpdate()
                        ->findOrFail($lockedReservation->line_id);

                    $this->availability->validateReservation(
                        $lockedReservation->shootingRange,
                        $lane,
                        $request->user(),
                        $lockedReservation->start,
                        $lockedReservation->end,
                        $lockedReservation->positions_count,
                        true,
                        $lockedReservation->is_dynamic_shooting,
                        $lockedReservation->id
                    );
                }

                $attributes = [
                    'status' => $validated['status'],
                    'mod_id' => $request->user()->id,
                ];

                if ($validated['status'] === Reservation::STATUS_APPROVED) {
                    $attributes += [
                        'approved_by_user_id' => $request->user()->id,
                        'approved_at' => now(),
                        'cancelled_by_user_id' => null,
                        'cancelled_at' => null,
                        'cancellation_reason' => null,
                    ];
                } elseif ($validated['status'] === Reservation::STATUS_CANCELLED) {
                    $attributes += [
                        'cancelled_by_user_id' => $request->user()->id,
                        'cancelled_at' => now(),
                        'cancellation_reason' => $validated['cancellation_reason'],
                    ];

                    if ($lockedReservation->payment_status === Reservation::PAYMENT_PENDING) {
                        $attributes['payment_status'] = Reservation::PAYMENT_EXPIRED;
                        $attributes['payment_expires_at'] = null;
                        $lockedReservation->payments()
                            ->whereIn('status', [
                                Payment::STATUS_CREATED,
                                Payment::STATUS_REGISTERED,
                                Payment::STATUS_PENDING,
                            ])
                            ->update([
                                'status' => Payment::STATUS_EXPIRED,
                                'failed_at' => now(),
                            ]);
                    }
                } elseif (in_array($validated['status'], [
                    Reservation::STATUS_COMPLETED,
                    Reservation::STATUS_NO_SHOW,
                ], true)) {
                    $attributes += [
                        'finalized_by_user_id' => $request->user()->id,
                        'finalized_at' => now(),
                        'cancellation_reason' => null,
                    ];
                } else {
                    $attributes += [
                        'approved_by_user_id' => null,
                        'approved_at' => null,
                        'cancelled_by_user_id' => null,
                        'cancelled_at' => null,
                        'cancellation_reason' => null,
                        'finalized_by_user_id' => null,
                        'finalized_at' => null,
                    ];
                }

                $lockedReservation->update($attributes);
                $statusChanged = true;
            });
        } catch (ValidationException $exception) {
            return $this->reservationValidationRedirect($exception);
        }

        if ($statusChanged) {
            if ($validated['status'] === Reservation::STATUS_CANCELLED) {
                $refundResult = $this->refundReservationAfterCancellation(
                    $reservation->fresh(['shootingRange.paymentAccount', 'payments'])
                );
            }

            $this->sendReservationNotifications(
                $reservation->fresh(),
                ReservationNotificationMail::EVENT_STATUS_CHANGED,
                notifyStaff: false
            );
            $this->security->recordIpAddress($request->user(), $request, 'reservation.status_changed');
            $this->security->audit('reservation.status_changed', $request->user(), $request, $reservation->fresh(), [
                'status' => $validated['status'],
                'shooting_range_id' => $reservation->shooting_range_id,
            ]);
        }

        if ($refundResult['failed']) {
            return back()->with(
                'error',
                'Rezerwacja została anulowana, ale nie udało się zlecić zwrotu. '.$refundResult['message']
            );
        }

        return back()->with('success', $statusChanged
            ? 'Status rezerwacji został zmieniony.'.($refundResult['message'] ? ' '.$refundResult['message'] : '')
            : 'Status rezerwacji nie wymagał zmiany.');
    }

    public function destroyByStaff(
        Request $request,
        Reservation $reservation
    ): RedirectResponse {
        abort_unless(
            $request->user()->administersShootingRange($reservation->shootingRange),
            403
        );

        $validated = $request->validate([
            'deletion_reason' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'deletion_reason.required' => 'Podaj powód ukrycia rezerwacji.',
            'deletion_reason.min' => 'Powód ukrycia powinien zawierać co najmniej 5 znaków.',
        ]);

        $actor = $request->user();
        $hiddenReservation = null;

        DB::transaction(function () use (
            $actor,
            &$hiddenReservation,
            $reservation,
            $validated
        ): void {
            $hiddenReservation = Reservation::query()
                ->whereKey($reservation->id)
                ->with([
                    'shootingRange',
                    'lane',
                    'customer',
                    'user',
                    'creator',
                    'approver',
                    'canceller',
                    'finalizer',
                ])
                ->lockForUpdate()
                ->firstOrFail();

            if (! $hiddenReservation->canBeHiddenByStaff()) {
                $this->throwReservationError(
                    'Tej rezerwacji nie można ukryć. Najpierw ją anuluj i zakończ wymagane rozliczenia płatności.'
                );
            }

            if (! $hiddenReservation->isCancelled()) {
                $hiddenReservation->update([
                    'status' => Reservation::STATUS_CANCELLED,
                    'cancelled_by_user_id' => $actor->id,
                    'cancelled_at' => now(),
                    'cancellation_reason' => 'Wpis ukryty przez administratora: '.$validated['deletion_reason'],
                ]);

                $hiddenReservation->payments()
                    ->whereIn('status', [
                        Payment::STATUS_CREATED,
                        Payment::STATUS_REGISTERED,
                        Payment::STATUS_PENDING,
                    ])
                    ->update([
                        'status' => Payment::STATUS_EXPIRED,
                        'failed_at' => now(),
                    ]);
            }

            $hiddenReservation->update([
                'deleted_by_user_id' => $actor->id,
                'deletion_reason' => $validated['deletion_reason'],
            ]);
            $hiddenReservation->delete();
        });

        if ($hiddenReservation) {
            $this->sendReservationNotifications(
                $hiddenReservation,
                ReservationNotificationMail::EVENT_DELETED
            );

            $this->security->recordIpAddress(
                $actor,
                $request,
                'reservation.hidden_by_admin'
            );
            $this->security->audit(
                'reservation.hidden_by_admin',
                $actor,
                $request,
                $hiddenReservation,
                [
                    'shooting_range_id' => $hiddenReservation->shooting_range_id,
                    'line_id' => $hiddenReservation->line_id,
                    'start' => $hiddenReservation->start?->toDateTimeString(),
                    'end' => $hiddenReservation->end?->toDateTimeString(),
                    'status' => $hiddenReservation->status,
                    'deletion_reason' => $validated['deletion_reason'],
                ]
            );
        }

        return back()->with('success', 'Rezerwacja została ukryta. Jej historia i płatności pozostały zachowane.');
    }

    public function approveChangeRequest(
        Request $request,
        ReservationChangeRequest $changeRequest
    ): RedirectResponse {
        $validated = $request->validate([
            'decision_note' => ['nullable', 'string', 'max:500'],
        ]);

        $changeRequest->loadMissing([
            'reservation.shootingRange.paymentAccount',
            'reservation.lane',
            'newLane',
        ]);
        $reservation = $changeRequest->reservation;

        abort_unless(
            $request->user()->managesShootingRange($reservation->shootingRange),
            403
        );

        $refundFailed = false;
        $refundMessage = null;

        try {
            DB::transaction(function () use (
                $changeRequest,
                $request,
                $reservation,
                $validated
            ): void {
                $lockedRequest = ReservationChangeRequest::query()
                    ->whereKey($changeRequest->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! $lockedRequest->isPending()) {
                    $this->throwReservationError('Ten wniosek został już obsłużony.');
                }

                $lockedReservation = Reservation::query()
                    ->whereKey($reservation->id)
                    ->with(['shootingRange.paymentAccount', 'lane', 'payments'])
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($lockedRequest->type === ReservationChangeRequest::TYPE_CHANGE) {
                    $lane = Lane::query()
                        ->where('shooting_range_id', $lockedReservation->shooting_range_id)
                        ->where('is_archived', false)
                        ->lockForUpdate()
                        ->findOrFail($lockedRequest->new_lane_id);

                    $this->availability->validateReservation(
                        $lockedReservation->shootingRange,
                        $lane,
                        $request->user(),
                        $lockedRequest->new_start,
                        $lockedRequest->new_end,
                        $lockedRequest->new_positions_count,
                        true,
                        (bool) $lockedRequest->new_is_dynamic_shooting,
                        $lockedReservation->id
                    );

                    $lockedReservation->update([
                        'mod_id' => $request->user()->id,
                        'line_id' => $lane->id,
                        'start' => $lockedRequest->new_start,
                        'end' => $lockedRequest->new_end,
                        'positions_count' => $lockedRequest->new_positions_count,
                        'is_dynamic_shooting' => $lockedRequest->new_is_dynamic_shooting,
                        'has_shooting_leader_license' => $lockedRequest->new_has_shooting_leader_license,
                        'shooting_leader_license_number' => $lockedRequest->new_shooting_leader_license_number,
                    ]);
                } else {
                    $lockedReservation->update([
                        'status' => Reservation::STATUS_CANCELLED,
                        'cancelled_by_user_id' => $request->user()->id,
                        'cancelled_at' => now(),
                        'cancellation_reason' => $lockedRequest->customer_note
                            ?: 'Anulowanie zatwierdzone przez personel.',
                    ]);

                }

                $lockedRequest->update([
                    'status' => ReservationChangeRequest::STATUS_APPROVED,
                    'decided_by_user_id' => $request->user()->id,
                    'decision_note' => $validated['decision_note'] ?? null,
                    'decided_at' => now(),
                ]);
            });
        } catch (ValidationException $exception) {
            return $this->reservationValidationRedirect($exception);
        }

        $freshRequest = $changeRequest->fresh([
            'reservation.shootingRange.paymentAccount',
            'reservation.lane',
            'reservation.payments',
            'requester',
            'oldLane',
            'newLane',
        ]);

        if ($freshRequest->type === ReservationChangeRequest::TYPE_CANCEL) {
            $refundResult = $this->refundReservationAfterCancellation(
                $freshRequest->reservation
            );
            $refundFailed = $refundResult['failed'];
            $refundMessage = $refundResult['message'];
        }

        $this->notifyReservationChangeRequest(
            $freshRequest,
            ReservationChangeRequestMail::EVENT_APPROVED,
            notifyStaff: false
        );
        $this->security->recordIpAddress($request->user(), $request, 'reservation_change_request.approved');
        $this->security->audit('reservation_change_request.approved', $request->user(), $request, $freshRequest, [
            'reservation_id' => $freshRequest->reservation_id,
            'type' => $freshRequest->type,
        ]);

        return back()->with(
            $refundFailed ? 'error' : 'success',
            $refundFailed
                ? 'Wniosek zatwierdzono i rezerwację anulowano, ale zwrot wymaga obsługi. '.$refundMessage
                : 'Wniosek został zatwierdzony.'.($refundMessage ? ' '.$refundMessage : '')
        );
    }

    public function rejectChangeRequest(
        Request $request,
        ReservationChangeRequest $changeRequest
    ): RedirectResponse {
        $validated = $request->validate([
            'decision_note' => ['nullable', 'string', 'max:500'],
        ]);

        $changeRequest->loadMissing('reservation.shootingRange');

        abort_unless(
            $request->user()->managesShootingRange($changeRequest->reservation->shootingRange),
            403
        );

        abort_unless($changeRequest->isPending(), 422, 'Ten wniosek został już obsłużony.');

        $changeRequest->update([
            'status' => ReservationChangeRequest::STATUS_REJECTED,
            'decided_by_user_id' => $request->user()->id,
            'decision_note' => $validated['decision_note'] ?? null,
            'decided_at' => now(),
        ]);

        $this->notifyReservationChangeRequest(
            $changeRequest->fresh([
                'reservation.shootingRange',
                'reservation.lane',
                'requester',
                'oldLane',
                'newLane',
            ]),
            ReservationChangeRequestMail::EVENT_REJECTED,
            notifyStaff: false
        );
        $this->security->recordIpAddress($request->user(), $request, 'reservation_change_request.rejected');
        $this->security->audit('reservation_change_request.rejected', $request->user(), $request, $changeRequest, [
            'reservation_id' => $changeRequest->reservation_id,
            'type' => $changeRequest->type,
        ]);

        return back()->with('success', 'Wniosek został odrzucony.');
    }

    public function updateReservationNotifications(
        Request $request
    ): RedirectResponse {
        $validated = $request->validate([
            'shooting_range_id' => ['required', 'integer', 'exists:shooting_ranges,id'],
            'receives_reservation_notifications' => ['nullable', 'boolean'],
        ]);

        $shootingRange = ShootingRange::findOrFail(
            $validated['shooting_range_id']
        );
        $user = $request->user();

        abort_unless(
            ! $user->isSuperAdmin() && $user->managesShootingRange($shootingRange),
            403
        );

        abort_unless(
            Schema::hasColumn(
                'shooting_range_employee',
                'receives_reservation_notifications'
            ),
            503,
            'Ustawienia powiadomień wymagają uruchomienia migracji bazy danych.'
        );

        $user->shootingRanges()->updateExistingPivot($shootingRange->id, [
            'receives_reservation_notifications' => $request->boolean(
                'receives_reservation_notifications'
            ),
        ]);

        return back()->with(
            'success',
            'Zapisano ustawienia powiadomień o rezerwacjach.'
        );
    }

    private function sendReservationNotifications(
        ?Reservation $reservation,
        string $eventType,
        bool $notifyStaff = true
    ): void {
        if (! $reservation) {
            return;
        }

        $reservation->loadMissing([
            'shootingRange',
            'lane',
            'customer',
            'user',
        ]);

        $recipients = [];
        $customerEmail = $reservation->customer_email
            ?? $reservation->customer?->email
            ?? (
                $reservation->source === Reservation::SOURCE_STAFF_GUEST
                    ? null
                    : $reservation->user?->email
            );

        if ($customerEmail && filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
            $recipients[mb_strtolower($customerEmail)] = [
                'email' => $customerEmail,
                'type' => 'customer',
            ];
        }

        $staffMembers = collect();

        if (
            $notifyStaff
            &&
            Schema::hasColumn(
                'shooting_range_employee',
                'receives_reservation_notifications'
            )
        ) {
            $staffMembers = $reservation->shootingRange?->employees()
                ->wherePivot('is_approved', true)
                ->wherePivot('receives_reservation_notifications', true)
                ->where('users.is_approved', true)
                ->get(['users.id', 'users.email']) ?? collect();
        }

        foreach ($staffMembers as $staffMember) {
            if (! filter_var($staffMember->email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            $recipients[mb_strtolower($staffMember->email)] = [
                'email' => $staffMember->email,
                'type' => 'staff',
            ];
        }

        foreach ($recipients as $recipient) {
            try {
                Mail::to($recipient['email'])->send(
                    new ReservationNotificationMail(
                        $reservation,
                        $eventType,
                        $recipient['type']
                    )
                );
            } catch (\Throwable $exception) {
                Log::warning('Nie udało się wysłać maila o rezerwacji.', [
                    'reservation_id' => $reservation->id,
                    'event_type' => $eventType,
                    'recipient_type' => $recipient['type'],
                    'recipient_email' => $recipient['email'],
                    'exception' => $exception->getMessage(),
                ]);
            }
        }
    }

    private function refundReservationAfterCancellation(
        Reservation $reservation
    ): array {
        if (! $reservation->isPaid()) {
            return ['failed' => false, 'message' => null];
        }

        $payment = $reservation->payments
            ->whereIn('status', [
                Payment::STATUS_PAID,
                Payment::STATUS_PARTIALLY_REFUNDED,
            ])
            ->sortByDesc('created_at')
            ->first();

        if (! $payment) {
            return [
                'failed' => true,
                'message' => 'Nie znaleziono opłaconej transakcji powiązanej z rezerwacją.',
            ];
        }

        if ($payment->method !== 'p24') {
            $payment->update([
                'status' => Payment::STATUS_REFUND_PENDING,
                'refund_requested_amount_minor' => max(
                    0,
                    (int) $payment->amount_minor - (int) $payment->refunded_amount_minor
                ),
                'refund_error' => 'Zwrot płatności manualnej wymaga potwierdzenia przez administratora.',
            ]);

            return [
                'failed' => false,
                'message' => 'Płatność gotówkowa lub przelew tradycyjny wymaga ręcznego zwrotu i potwierdzenia w rozliczeniach.',
            ];
        }

        $account = $reservation->shootingRange?->paymentAccount;

        if (! $account) {
            return [
                'failed' => true,
                'message' => 'Strzelnica nie ma dostępnej konfiguracji Przelewy24.',
            ];
        }

        try {
            $refunded = $this->przelewy24->refundForAccount(
                $payment,
                $account,
                max(0, (int) $payment->amount_minor - (int) $payment->refunded_amount_minor),
                'Zwrot za anulowaną rezerwację #'.$reservation->id
            );
        } catch (\Throwable $exception) {
            Log::error('Nie udało się zlecić zwrotu za rezerwację.', [
                'reservation_id' => $reservation->id,
                'payment_id' => $payment->id,
                'exception' => $exception->getMessage(),
            ]);

            return ['failed' => true, 'message' => $exception->getMessage()];
        }

        if (! $refunded) {
            return [
                'failed' => true,
                'message' => $payment->fresh()->refund_error
                    ?: 'Operator płatności odrzucił dyspozycję zwrotu.',
            ];
        }

        $reservation->update([
            'payment_status' => Reservation::PAYMENT_REFUNDED,
        ]);

        return [
            'failed' => false,
            'message' => 'Zwrot płatności P24 został zlecony.',
        ];
    }

    private function requestPaidReservationChange(
        Request $request,
        Reservation $reservation,
        User $actor
    ): RedirectResponse {
        $validated = $request->validate([
            'shooting_range_id' => ['required', 'integer', 'exists:shooting_ranges,id'],
            'line_id' => ['required', 'integer', 'exists:lanes,id'],
            'date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            'has_shooting_leader_license' => ['nullable', 'boolean'],
            'shooting_leader_license_number' => ['nullable', 'string', 'max:100'],
        ]);

        try {
            DB::transaction(function () use (
                $actor,
                $reservation,
                $validated
            ): void {
                $lockedReservation = Reservation::query()
                    ->whereKey($reservation->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (
                    ! in_array($lockedReservation->status, [
                        Reservation::STATUS_PENDING,
                        Reservation::STATUS_APPROVED,
                    ], true)
                    || ! $lockedReservation->canBeEditedByCustomer()
                ) {
                    $this->throwReservationError(
                        'Rezerwacja nie może już zostać edytowana.'
                    );
                }

                if (! $lockedReservation->isPaid()) {
                    $this->throwReservationError(
                        'Ta operacja dotyczy wyłącznie opłaconych rezerwacji.'
                    );
                }

                if ($lockedReservation->start->diffInHours(now()) < 24) {
                    $this->throwReservationError(
                        'Opłaconą rezerwację można zmienić najpóźniej 24 godziny przed rozpoczęciem.'
                    );
                }

                if ($lockedReservation->hasPendingChangeRequest()) {
                    $this->throwReservationError(
                        'Dla tej rezerwacji istnieje już wniosek oczekujący na decyzję personelu.'
                    );
                }

                if (
                    (int) $validated['shooting_range_id']
                    !== (int) $lockedReservation->shooting_range_id
                ) {
                    $this->throwReservationError(
                        'Nie można przenieść rezerwacji do innej strzelnicy.'
                    );
                }

                $shootingRange = $lockedReservation->shootingRange;
                $lane = Lane::query()
                    ->where('shooting_range_id', $shootingRange->id)
                    ->where('is_archived', false)
                    ->lockForUpdate()
                    ->findOrFail($validated['line_id']);
                $start = Carbon::createFromFormat(
                    'Y-m-d H:i',
                    $validated['date'].' '.$validated['start_time']
                );
                $durationMinutes = max(
                    30,
                    $lockedReservation->start->diffInMinutes($lockedReservation->end)
                );
                $end = $start->copy()->addMinutes($durationMinutes);
                $isDynamicShooting = (bool) $lockedReservation->is_dynamic_shooting;
                $positionsCount = $this->requestedPositions(
                    $lane,
                    (int) $lockedReservation->positions_count,
                    $isDynamicShooting
                );
                [$hasShootingLeaderLicense, $shootingLeaderLicenseNumber] =
                    $this->shootingLeaderData(
                        $shootingRange,
                        $validated,
                        $actor
                    );

                $this->availability->validateReservation(
                    $shootingRange,
                    $lane,
                    $actor,
                    $start,
                    $end,
                    $positionsCount,
                    false,
                    $isDynamicShooting,
                    $lockedReservation->id
                );

                $changeRequest = $this->createReservationChangeRequest(
                    $lockedReservation,
                    $actor,
                    ReservationChangeRequest::TYPE_CHANGE,
                    [
                        'new_lane_id' => $lane->id,
                        'new_start' => $start,
                        'new_end' => $end,
                        'new_positions_count' => $positionsCount,
                        'new_is_dynamic_shooting' => $isDynamicShooting,
                        'new_has_shooting_leader_license' => $hasShootingLeaderLicense,
                        'new_shooting_leader_license_number' => $shootingLeaderLicenseNumber,
                    ]
                );

                $this->notifyReservationChangeRequest(
                    $changeRequest,
                    ReservationChangeRequestMail::EVENT_SUBMITTED,
                    notifyCustomer: false
                );
            });
        } catch (ValidationException $exception) {
            return $this->reservationValidationRedirect($exception);
        }

        $this->security->recordIpAddress($actor, $request, 'reservation.change_requested_by_customer');
        $this->security->audit('reservation.change_requested_by_customer', $actor, $request, $reservation->fresh(), [
            'shooting_range_id' => $reservation->shooting_range_id,
        ]);

        return redirect()
            ->route('rezerwacje.mine')
            ->with('success', 'Wniosek o zmianę opłaconej rezerwacji został przekazany do zatwierdzenia przez personel strzelnicy.');
    }

    private function createReservationChangeRequest(
        Reservation $reservation,
        User $actor,
        string $type,
        array $attributes = []
    ): ReservationChangeRequest {
        return ReservationChangeRequest::create([
            'reservation_id' => $reservation->id,
            'requested_by_user_id' => $actor->id,
            'type' => $type,
            'status' => ReservationChangeRequest::STATUS_PENDING,
            'old_lane_id' => $reservation->line_id,
            'old_start' => $reservation->start,
            'old_end' => $reservation->end,
            'old_positions_count' => $reservation->positions_count,
            'old_is_dynamic_shooting' => $reservation->is_dynamic_shooting,
            'old_has_shooting_leader_license' => $reservation->has_shooting_leader_license,
            'old_shooting_leader_license_number' => $reservation->shooting_leader_license_number,
            'new_lane_id' => $attributes['new_lane_id'] ?? $reservation->line_id,
            'new_start' => $attributes['new_start'] ?? $reservation->start,
            'new_end' => $attributes['new_end'] ?? $reservation->end,
            'new_positions_count' => $attributes['new_positions_count'] ?? $reservation->positions_count,
            'new_is_dynamic_shooting' => $attributes['new_is_dynamic_shooting'] ?? $reservation->is_dynamic_shooting,
            'new_has_shooting_leader_license' => $attributes['new_has_shooting_leader_license'] ?? $reservation->has_shooting_leader_license,
            'new_shooting_leader_license_number' => $attributes['new_shooting_leader_license_number'] ?? $reservation->shooting_leader_license_number,
            'customer_note' => $attributes['customer_note'] ?? null,
        ]);
    }

    private function notifyReservationChangeRequest(
        ReservationChangeRequest $changeRequest,
        string $eventType,
        bool $notifyStaff = true,
        bool $notifyCustomer = true
    ): void {
        $changeRequest->loadMissing([
            'reservation.shootingRange.employees',
            'reservation.customer',
            'reservation.user',
            'requester',
            'oldLane',
            'newLane',
        ]);

        if ($notifyCustomer) {
            $customerEmail = $changeRequest->reservation->customer_email
                ?? $changeRequest->reservation->customer?->email
                ?? $changeRequest->reservation->user?->email;

            if ($customerEmail && filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
                Mail::to($customerEmail)->send(
                    new ReservationChangeRequestMail(
                        $changeRequest,
                        $eventType,
                        'customer'
                    )
                );
            }
        }

        if (! $notifyStaff) {
            return;
        }

        if (! Schema::hasColumn(
            'shooting_range_employee',
            'receives_reservation_notifications'
        )) {
            return;
        }

        $changeRequest->reservation->shootingRange?->employees()
            ->wherePivot('is_approved', true)
            ->wherePivot('receives_reservation_notifications', true)
            ->where('users.is_approved', true)
            ->get(['users.id', 'users.email'])
            ->each(function ($user) use ($changeRequest, $eventType): void {
                if (! filter_var($user->email, FILTER_VALIDATE_EMAIL)) {
                    return;
                }

                Mail::to($user->email)->send(
                    new ReservationChangeRequestMail(
                        $changeRequest,
                        $eventType,
                        'staff'
                    )
                );
            });
    }

    private function validateReservationInput(Request $request): array
    {
        $validated = $request->validate($this->reservationRules());
        $this->ensureReservationEndIsAfterStart($validated);

        return $validated;
    }

    private function validateStaffReservationInput(Request $request): array
    {
        $customerEmailRules = ['nullable', 'email', 'max:255'];
        $customerPhoneRules = ['nullable', 'string', 'max:50'];

        if ($request->input('customer_type') === 'guest') {
            array_unshift($customerEmailRules, 'required_without:customer_phone');
            array_unshift($customerPhoneRules, 'required_without:customer_email');
        }

        $validated = $request->validate([
            ...$this->reservationRules(),
            'customer_type' => ['required', Rule::in(['account', 'guest'])],
            'customer_user_id' => [
                'required_if:customer_type,account',
                'nullable',
                Rule::exists('users', 'id')->where('is_approved', true),
            ],
            'customer_name' => [
                'required_if:customer_type,guest',
                'nullable',
                'string',
                'max:255',
            ],
            'customer_email' => $customerEmailRules,
            'customer_phone' => $customerPhoneRules,
            'payment_method' => ['nullable', Rule::in([
                'none',
                'cash',
                'bank_transfer',
                'payment_link',
            ])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [
            'customer_type.required' => 'Wybierz rodzaj klienta.',
            'customer_type.in' => 'Wybrany rodzaj klienta jest nieprawidłowy.',
            'customer_user_id.required_if' => 'Dla użytkownika z kontem wyszukaj i wybierz aktywne konto przed zapisem.',
            'customer_user_id.exists' => 'Wybrane konto użytkownika nie istnieje albo nie jest aktywne.',
            'customer_name.required_if' => 'Dla gościa bez konta wpisz imię i nazwisko.',
            'customer_email.required_without' => 'Dla gościa bez konta podaj e-mail albo telefon.',
            'customer_email.email' => 'Wpisz poprawny adres e-mail klienta.',
            'customer_phone.required_without' => 'Dla gościa bez konta podaj telefon albo e-mail.',
            'shooting_range_id.required' => 'Nie wybrano strzelnicy.',
            'shooting_range_id.exists' => 'Wybrana strzelnica nie istnieje.',
            'line_id.required' => 'Wybierz oś strzelecką.',
            'line_id.exists' => 'Wybrana oś strzelecka nie istnieje.',
            'date.required' => 'Wybierz datę rezerwacji.',
            'date.date_format' => 'Data rezerwacji musi mieć format RRRR-MM-DD.',
            'start_time.required' => 'Wybierz godzinę rozpoczęcia.',
            'start_time.date_format' => 'Godzina rozpoczęcia musi mieć format GG:MM.',
            'end_time.required' => 'Wybierz godzinę zakończenia.',
            'end_time.regex' => 'Godzina zakończenia musi mieć format GG:MM.',
            'positions_count.required' => 'Wybierz liczbę stanowisk.',
            'positions_count.integer' => 'Liczba stanowisk musi być liczbą.',
            'positions_count.min' => 'Liczba stanowisk musi być większa od zera.',
        ]);
        $this->ensureReservationEndIsAfterStart($validated);

        return $validated;
    }

    private function reservationValidationRedirect(
        ValidationException $exception,
        ?string $staffModalMode = null,
        ?int $staffReservationId = null
    ): RedirectResponse {
        $message = $exception->errors()['reservation'][0]
            ?? collect($exception->errors())->flatten()->first()
            ?? 'Nie udało się zapisać rezerwacji.';

        $redirect = back()
            ->withInput()
            ->withErrors($exception->errors())
            ->with('error', $message);

        if ($staffModalMode) {
            $redirect->with('staff_reservation_modal', [
                'mode' => $staffModalMode,
                'reservation_id' => $staffReservationId,
                'message' => $message,
            ]);
        }

        return $redirect;
    }

    private function staffReservationErrorRedirect(
        string $message,
        string $mode,
        ?int $reservationId = null
    ): RedirectResponse {
        return back()
            ->withInput()
            ->withErrors([
                'reservation' => $message,
            ])
            ->with('error', $message)
            ->with('staff_reservation_modal', [
                'mode' => $mode,
                'reservation_id' => $reservationId,
                'message' => $message,
            ]);
    }

    private function reservationRules(): array
    {
        return [
            'shooting_range_id' => ['required', 'integer', 'exists:shooting_ranges,id'],
            'line_id' => ['required', 'integer', 'exists:lanes,id'],
            'date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => [
                'required',
                'regex:/^(?:(?:[01]\d|2[0-3]):[0-5]\d|24:00)$/',
            ],
            'positions_count' => ['required', 'integer', 'min:1'],
            'is_dynamic_shooting' => ['nullable', 'boolean'],
            'has_shooting_leader_license' => ['nullable', 'boolean'],
            'shooting_leader_license_number' => ['nullable', 'string', 'max:100'],
        ];
    }

    private function shootingLeaderData(
        ShootingRange $shootingRange,
        array $validated,
        ?User $customer = null
    ): array {
        $hasLicenseInput = array_key_exists(
            'has_shooting_leader_license',
            $validated
        );
        $hasLicense = (bool) (
            $validated['has_shooting_leader_license']
            ?? false
        );
        $licenseNumber = trim(
            (string) ($validated['shooting_leader_license_number'] ?? '')
        );

        if (
            ! $hasLicenseInput
            && $customer?->has_shooting_leader_license
        ) {
            $hasLicense = true;
            $licenseNumber = trim(
                (string) $customer->shooting_leader_license_number
            );
        }

        if (! $hasLicense) {
            $licenseNumber = '';
        }

        if ($hasLicense && $licenseNumber === '') {
            $this->throwReservationError(
                'Podaj numer legitymacji lub uprawnień prowadzącego strzelanie.'
            );
        }

        if (
            $shootingRange->requires_shooting_leader
            && (! $hasLicense || $licenseNumber === '')
        ) {
            $this->throwReservationError(
                'Ta strzelnica wymaga wskazania osoby posiadającej uprawnienia prowadzącego strzelanie. Zaznacz uprawnienia i podaj numer legitymacji lub uprawnień.'
            );
        }

        return [
            $hasLicense,
            $licenseNumber !== '' ? $licenseNumber : null,
        ];
    }

    private function requestedPositions(
        Lane $lane,
        int $positionsCount,
        bool $isDynamicShooting
    ): int {
        return $isDynamicShooting
            ? (int) $lane->numberOfPositions
            : $positionsCount;
    }

    private function publicReservationData(
        Reservation $reservation
    ): array {
        return [
            'id' => $reservation->id,
            'line_id' => $reservation->line_id,
            'start_date' => $reservation->start->format('Y-m-d'),
            'start_time' => $reservation->start->format('H:i'),
            'end_time' => $this->reservationEndTimeForCalendar($reservation),
            'status' => $reservation->status,
            'positions_count' => $reservation->positions_count ?? 1,
            'is_dynamic_shooting' => $reservation->is_dynamic_shooting,
            'has_shooting_leader_license' => $reservation->has_shooting_leader_license,
            'shooting_leader_license_number' => $reservation->shooting_leader_license_number,
            'title' => 'Rezerwacja',
        ];
    }

    private function reservationPeriod(array $validated): array
    {
        $start = Carbon::createFromFormat(
            'Y-m-d H:i',
            $validated['date'].' '.$validated['start_time']
        );

        $end = $validated['end_time'] === '24:00'
            ? Carbon::createFromFormat('Y-m-d', $validated['date'])
                ->addDay()
                ->startOfDay()
            : Carbon::createFromFormat(
                'Y-m-d H:i',
                $validated['date'].' '.$validated['end_time']
            );

        return [
            $start,
            $end,
        ];
    }

    private function ensureReservationEndIsAfterStart(array $validated): void
    {
        if (
            $this->timeToMinutes($validated['end_time'])
            <= $this->timeToMinutes($validated['start_time'])
        ) {
            $this->throwReservationError(
                'Godzina zakończenia musi być późniejsza niż godzina rozpoczęcia.'
            );
        }
    }

    private function timeToMinutes(string $time): int
    {
        if ($time === '24:00') {
            return 24 * 60;
        }

        [$hours, $minutes] = array_map('intval', explode(':', $time));

        return ($hours * 60) + $minutes;
    }

    private function reservationEndTimeForCalendar(Reservation $reservation): string
    {
        if (
            $reservation->end->format('H:i') === '00:00'
            && $reservation->end->greaterThan($reservation->start)
            && ! $reservation->end->isSameDay($reservation->start)
        ) {
            return '24:00';
        }

        return $reservation->end->format('H:i');
    }

    private function authorizeCustomerReservation(
        Request $request,
        Reservation $reservation
    ): void {
        $userId = (int) $request->user()->id;
        $isOwner = (int) $reservation->customer_user_id === $userId
            || (
                $reservation->customer_user_id === null
                && (int) $reservation->user_id === $userId
                && in_array($reservation->source, [
                    null,
                    Reservation::SOURCE_ONLINE_ACCOUNT,
                ], true)
            );

        abort_unless($isOwner, 403);
    }

    private function openingHoursMap(ShootingRange $shootingRange)
    {
        return $shootingRange->openingHours
            ->mapWithKeys(fn ($openingHour) => [
                (int) $openingHour->day_of_week => [
                    'opens_at' => Carbon::parse($openingHour->opens_at)->format('H:i'),
                    'closes_at' => Carbon::parse($openingHour->closes_at)->format('H:i'),
                ],
            ]);
    }

    private function throwReservationError(string $message): never
    {
        throw ValidationException::withMessages([
            'reservation' => $message,
        ]);
    }

    private function calendarPeriod(string $viewMode, Carbon $date): array
    {
        return match ($viewMode) {
            'month' => [
                $date->copy()->startOfMonth()->startOfWeek(),
                $date->copy()->endOfMonth()->endOfWeek()->endOfDay(),
            ],
            'week' => [
                $date->copy()->startOfWeek(),
                $date->copy()->endOfWeek()->endOfDay(),
            ],
            default => [
                $date->copy()->startOfDay(),
                $date->copy()->endOfDay(),
            ],
        };
    }
}

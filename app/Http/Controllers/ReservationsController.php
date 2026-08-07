<?php

namespace App\Http\Controllers;

use App\Mail\ReservationNotificationMail;
use App\Models\Lane;
use App\Models\Reservation;
use App\Models\ReservationArchive;
use App\Models\ShootingRange;
use App\Models\User;
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
            ->get(['id', 'name', 'email', 'telephone']);

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
            $reservation->canBeEdited(),
            403,
            'Termin tej rezerwacji już upłynął.'
        );

        $shootingRange = $reservation->shootingRange()
            ->with(['lanes', 'openingHours'])
            ->firstOrFail();

        return view('reservation.edit', [
            'reservation' => $reservation,
            'shootingRange' => $shootingRange,
            'openingHours' => $this->openingHoursMap($shootingRange),
            'maxBookingDate' => $shootingRange->latestBookableDate(),
        ]);
    }

    public function updateByCustomer(
        Request $request,
        Reservation $reservation
    ): RedirectResponse {
        $this->authorizeCustomerReservation($request, $reservation);
        $validated = $this->validateReservationInput($request);
        $actor = $request->user();

        try {
            DB::transaction(function () use (
                $reservation,
                $validated,
                $actor
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
                    || ! $lockedReservation->canBeEdited()
                ) {
                    $this->throwReservationError(
                        'Rezerwacja nie może już zostać edytowana.'
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

                $lockedReservation->update([
                    'mod_id' => $actor->id,
                    'line_id' => $lane->id,
                    'start' => $start,
                    'end' => $end,
                    'positions_count' => $positionsCount,
                    'is_dynamic_shooting' => $isDynamicShooting,
                    'status' => Reservation::STATUS_PENDING,
                    'approved_by_user_id' => null,
                    'approved_at' => null,
                ]);
            });
        } catch (ValidationException $exception) {
            return $this->reservationValidationRedirect($exception);
        }

        $this->sendReservationNotifications(
            $reservation->fresh(),
            ReservationNotificationMail::EVENT_UPDATED
        );
        $this->security->recordIpAddress($actor, $request, 'reservation.updated_by_customer');
        $this->security->audit('reservation.updated_by_customer', $actor, $request, $reservation->fresh(), [
            'shooting_range_id' => $reservation->shooting_range_id,
        ]);

        return redirect()
            ->route('rezerwacje.mine')
            ->with('success', 'Rezerwacja została zaktualizowana.');
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
            ])
            ->orderBy('start')
            ->get()
            ->map(fn (Reservation $reservation) => [
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
                'status' => $reservation->status,
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
                'can_delete' => true,
            ])
            ->values();

        $openingHours = $shootingRange->openingHours
            ->mapWithKeys(fn ($openingHour) => [
                (int) $openingHour->day_of_week => [
                    'opens_at' => Carbon::parse($openingHour->opens_at)->format('H:i'),
                    'closes_at' => Carbon::parse($openingHour->closes_at)->format('H:i'),
                ],
            ]);

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
        ]);
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

        try {
            $reservation = DB::transaction(function () use ($validated, $actor): Reservation {
                $shootingRange = ShootingRange::findOrFail(
                    $validated['shooting_range_id']
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
                    false,
                    $isDynamicShooting
                );

                $requiresConfirmation = (bool) $shootingRange
                    ->requires_reservation_confirmation;
                $status = $requiresConfirmation
                    ? Reservation::STATUS_PENDING
                    : Reservation::STATUS_APPROVED;

                return Reservation::create([
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
                    'status' => $status,
                    'approved_at' => $requiresConfirmation ? null : now(),
                ]);
            });
        } catch (ValidationException $exception) {
            return $this->reservationValidationRedirect($exception);
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

        try {
            $validated = $this->validateStaffReservationInput($request);

            $reservation = DB::transaction(function () use ($validated, $actor): Reservation {
                $shootingRange = ShootingRange::findOrFail(
                    $validated['shooting_range_id']
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
                    $isDynamicShooting
                );

                $customer = $validated['customer_type'] === 'account'
                    ? User::query()
                        ->where('is_approved', true)
                        ->findOrFail($validated['customer_user_id'])
                    : null;
                $isGuest = $customer === null;

                return Reservation::create([
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
                    'status' => Reservation::STATUS_APPROVED,
                    'approved_by_user_id' => $actor->id,
                    'approved_at' => now(),
                    'notes' => $validated['notes'] ?? null,
                ]);
            });
        } catch (ValidationException $exception) {
            return $this->reservationValidationRedirect($exception, 'create');
        } catch (ModelNotFoundException) {
            return $this->staffReservationErrorRedirect(
                'Nie udało się odnaleźć wybranej strzelnicy, osi lub użytkownika. Odśwież kalendarz i spróbuj ponownie.',
                'create'
            );
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

        try {
            $validated = $this->validateStaffReservationInput($request);

            DB::transaction(function () use (
                $reservation,
                $validated,
                $actor
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

        return back()->with('success', 'Rezerwacja została zaktualizowana.');
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

        return back()->with(
            'success',
            $statusChanged
                ? 'Status rezerwacji został zmieniony.'
                : 'Status rezerwacji nie wymagał zmiany.'
        );
    }

    public function destroyByStaff(
        Request $request,
        Reservation $reservation
    ): RedirectResponse {
        abort_unless(
            $request->user()->managesShootingRange($reservation->shootingRange),
            403
        );

        $actor = $request->user();
        $deletedReservation = null;

        DB::transaction(function () use (
            &$deletedReservation,
            $reservation
        ): void {
            $deletedReservation = Reservation::query()
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

            $deletedReservation->delete();
        });

        if ($deletedReservation) {
            $this->sendReservationNotifications(
                $deletedReservation,
                ReservationNotificationMail::EVENT_DELETED
            );

            $this->security->recordIpAddress(
                $actor,
                $request,
                'reservation.deleted_by_staff'
            );
            $this->security->audit(
                'reservation.deleted_by_staff',
                $actor,
                $request,
                $deletedReservation,
                [
                    'shooting_range_id' => $deletedReservation->shooting_range_id,
                    'line_id' => $deletedReservation->line_id,
                    'start' => $deletedReservation->start?->toDateTimeString(),
                    'end' => $deletedReservation->end?->toDateTimeString(),
                    'status' => $deletedReservation->status,
                ]
            );
        }

        return back()->with('success', 'Rezerwacja została usunięta.');
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

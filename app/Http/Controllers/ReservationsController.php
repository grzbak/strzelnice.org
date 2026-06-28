<?php

namespace App\Http\Controllers;

use App\Models\Lane;
use App\Models\Reservation;
use App\Models\ShootingRange;
use App\Models\User;
use App\Services\ShootingRangeAvailabilityService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ReservationsController extends Controller
{
    public function __construct(
        private readonly ShootingRangeAvailabilityService $availability
    ) {}

    public function callendar(int $id): View|RedirectResponse
    {
        $shootingRange = ShootingRange::query()
            ->where('is_visible', true)
            ->where('is_archived', false)
            ->with(['lanes', 'openingHours', 'holidays', 'intervals'])
            ->findOrFail($id);

        $maxAdvanceBookingDays = $shootingRange->effectiveMaxAdvanceBookingDays();
        $maxBookingDate = $shootingRange->latestBookableDate();
        $requestedDate = Carbon::parse(
            request('date', now()->format('Y-m-d'))
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

        if (! $this->availability->canUserAccessRange($shootingRange, $user, $requestedDate)) {
            if (! $user) {
                return redirect()
                    ->route('login')
                    ->with('error', 'Zaloguj się, aby uzyskać dostęp do rezerwacji tej strzelnicy.');
            }

            abort(403, 'Nie posiadasz aktywnego zaproszenia do tej strzelnicy.');
        }

        $requirements = $this->availability->basicRequirements(
            $shootingRange,
            $requestedDate
        );

        if (! $requirements['allowed']) {
            session()->flash('error', $requirements['reason']);
        }

        $calendarDate = $requestedDate->format('Y-m-d');
        $reservations = Reservation::query()
            ->where('shooting_range_id', $shootingRange->id)
            ->whereIn('status', [
                Reservation::STATUS_PENDING,
                Reservation::STATUS_APPROVED,
            ])
            ->whereDate('start', $requestedDate)
            ->get()
            ->map(fn (Reservation $reservation) => $this->publicReservationData(
                $reservation
            ));

        return view('reservation.calendar-day-test', compact(
            'shootingRange',
            'calendarDate',
            'reservations',
            'maxAdvanceBookingDays',
            'maxBookingDate'
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

        if (! $this->availability->canUserAccessRange(
            $shootingRange,
            $request->user(),
            $date
        )) {
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

    public function searchCustomers(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'shooting_range_id' => [
                'required',
                'integer',
                'exists:shooting_ranges,id',
            ],
            'q' => ['required', 'string', 'min:2', 'max:255'],
        ]);
        $shootingRange = ShootingRange::findOrFail(
            $validated['shooting_range_id']
        );

        abort_unless(
            $request->user()->managesShootingRange($shootingRange),
            403
        );

        $term = mb_strtolower(trim($validated['q']));
        $users = User::query()
            ->where('is_approved', true)
            ->whereNotNull('email_verified_at')
            ->where(function ($query) use ($term) {
                $like = '%'.$term.'%';
                $query
                    ->whereRaw('LOWER(name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(email) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(telephone, \'\')) LIKE ?', [$like]);
            })
            ->orderBy('name')
            ->limit(8)
            ->get(['id', 'name', 'email', 'telephone']);

        return response()->json([
            'users' => $users,
        ]);
    }

    public function adminCalendar(Request $request): View
    {
        $validated = $request->validate([
            'view' => ['nullable', 'in:day,week,month'],
            'date' => ['nullable', 'date'],
            'shooting_range_id' => ['nullable', 'integer', 'exists:shooting_ranges,id'],
        ]);

        $user = $request->user();
        $rangesQuery = $user->isSuperAdmin()
            ? ShootingRange::query()
            : $user->shootingRanges()->wherePivot('is_approved', true);

        $managedRanges = $rangesQuery
            ->orderBy('name')
            ->get(['shooting_ranges.id', 'shooting_ranges.name']);

        $shootingRangeId = (int) (
            $validated['shooting_range_id']
            ?? $managedRanges->first()?->id
        );

        abort_if($shootingRangeId === 0, 404, 'Nie przypisano żadnej strzelnicy.');

        $shootingRange = ShootingRange::with(['lanes', 'openingHours'])
            ->findOrFail($shootingRangeId);

        abort_unless(
            $user->managesShootingRange($shootingRange),
            403,
            'Nie masz uprawnień do tej strzelnicy.'
        );

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
            ->with(['customer:id,name,email,telephone', 'user:id,name,email,telephone', 'lane'])
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
                    ?? $reservation->user?->email,
                'user_phone' => $reservation->customer_phone
                    ?? $reservation->customer?->telephone
                    ?? $reservation->user?->telephone,
                'source' => $reservation->source,
                'line_id' => $reservation->line_id,
                'lane_name' => $reservation->lane?->name ?? 'Usunięta oś',
                'lane_length' => $reservation->lane?->length,
                'start' => $reservation->start->format('Y-m-d H:i'),
                'end' => $reservation->end->format('Y-m-d H:i'),
                'date' => $reservation->start->format('Y-m-d'),
                'start_time' => $reservation->start->format('H:i'),
                'end_time' => $reservation->end->format('H:i'),
                'positions_count' => $reservation->positions_count,
                'is_dynamic_shooting' => $reservation->is_dynamic_shooting,
                'status' => $reservation->status,
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
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateReservationInput($request);
        $actor = $request->user();

        try {
            DB::transaction(function () use ($validated, $actor) {
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

                Reservation::create([
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
                    'status' => Reservation::STATUS_PENDING,
                ]);
            });
        } catch (ValidationException $exception) {
            return $this->reservationValidationRedirect($exception);
        }

        return back()->with('success', 'Rezerwacja została dodana i oczekuje na zatwierdzenie.');
    }

    public function storeByStaff(Request $request): RedirectResponse
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
        ]);

        $actor = $request->user();

        try {
            DB::transaction(function () use ($validated, $actor) {
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

                Reservation::create([
                    // Kolumny zgodnościowe pozostają do czasu drugiego etapu migracji.
                    'user_id' => $customer?->id ?? $actor->id,
                    'mod_id' => $actor->id,
                    'customer_user_id' => $customer?->id,
                    'customer_name' => $customer?->name
                        ?? ($validated['customer_name'] ?? 'Brak danych'),
                    'customer_email' => $customer?->email
                        ?? $validated['customer_email'],
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
            return $this->reservationValidationRedirect($exception);
        }

        return back()->with('success', 'Rezerwacja została zapisana i zatwierdzona.');
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
            ],
            Reservation::STATUS_APPROVED => [
                Reservation::STATUS_CANCELLED,
            ],
            Reservation::STATUS_CANCELLED => [
                Reservation::STATUS_PENDING,
                Reservation::STATUS_APPROVED,
            ],
        ];

        if ($validated['status'] === $reservation->status) {
            return back()->with('success', 'Status rezerwacji nie wymagał zmiany.');
        }

        if (! in_array(
            $validated['status'],
            $allowedTransitions[$reservation->status] ?? [],
            true
        )) {
            return back()->with(
                'error',
                'Ta zmiana statusu rezerwacji jest niedozwolona.'
            );
        }

        try {
            DB::transaction(function () use (
                $request,
                $reservation,
                $validated
            ) {
                $lockedReservation = Reservation::query()
                    ->whereKey($reservation->id)
                    ->lockForUpdate()
                    ->firstOrFail();

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
                } else {
                    $attributes += [
                        'approved_by_user_id' => null,
                        'approved_at' => null,
                        'cancelled_by_user_id' => null,
                        'cancelled_at' => null,
                        'cancellation_reason' => null,
                    ];
                }

                $lockedReservation->update($attributes);
            });
        } catch (ValidationException $exception) {
            return $this->reservationValidationRedirect($exception);
        }

        return back()->with('success', 'Status rezerwacji został zmieniony.');
    }

    private function validateReservationInput(Request $request): array
    {
        return $request->validate($this->reservationRules());
    }

    private function reservationValidationRedirect(
        ValidationException $exception
    ): RedirectResponse {
        $message = $exception->errors()['reservation'][0]
            ?? 'Nie udało się zapisać rezerwacji.';

        return back()
            ->withInput()
            ->with('error', $message);
    }

    private function reservationRules(): array
    {
        return [
            'shooting_range_id' => ['required', 'integer', 'exists:shooting_ranges,id'],
            'line_id' => ['required', 'integer', 'exists:lanes,id'],
            'date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
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
            'end_time' => $reservation->end->format('H:i'),
            'status' => $reservation->status,
            'positions_count' => $reservation->positions_count ?? 1,
            'is_dynamic_shooting' => $reservation->is_dynamic_shooting,
            'title' => 'Rezerwacja',
        ];
    }

    private function reservationPeriod(array $validated): array
    {
        return [
            Carbon::createFromFormat(
                'Y-m-d H:i',
                $validated['date'].' '.$validated['start_time']
            ),
            Carbon::createFromFormat(
                'Y-m-d H:i',
                $validated['date'].' '.$validated['end_time']
            ),
        ];
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

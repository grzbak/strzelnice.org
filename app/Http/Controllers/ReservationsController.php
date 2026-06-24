<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Models\ShootingRange;
use App\Models\Reservation;
use App\Models\Lane;

class ReservationsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    public function callendar($id)
    {
        $shootingRange = ShootingRange::query()
            ->where('is_visible', true)
            ->whereHas('employees', function ($query) {
                $query
                    ->where('shooting_range_employee.role', 'admin')
                    ->where('shooting_range_employee.is_approved', true)
                    ->where('users.is_approved', true);
            })
            ->with([
                'lanes',
                'openingHours',
                'holidays',
                'intervals',
            ])
            ->findOrFail($id);

        $calendarDate = request('date', now()->format('Y-m-d'));

        $reservations = Reservation::query()
            ->where('shooting_range_id', $shootingRange->id)
            ->whereIn('status', ['pending', 'approved'])
            ->whereDate('start', '>=', Carbon::parse($calendarDate)->copy()->subDays(7))
            ->whereDate('start', '<=', Carbon::parse($calendarDate)->copy()->addDays(14))
            ->get()
            ->map(function ($reservation) {
                return [
                    'id' => $reservation->id,
                    'line_id' => $reservation->line_id,
                    'start_date' => $reservation->start->format('Y-m-d'),
                    'start_time' => $reservation->start->format('H:i'),
                    'end_time' => $reservation->end->format('H:i'),
                    'status' => $reservation->status,
                    'positions_count' => $reservation->positions_count ?? 1,
                    'title' => 'Rezerwacja',
                ];
            });

        return view('reservation.calendar-day-test', compact(
            'shootingRange',
            'calendarDate',
            'reservations'
        ));
    }

    public function events(Request $request)
    {
        // optional: range of dates
        $start = $request->query('start');
        $end   = $request->query('end');

        $events = Reservation::whereBetween('start', [$start, $end])->get();

        return response()->json(
            $events->map(fn($r) => [
                'id'         => $r->id,
                'resourceId' => $r->axis_id,
                'start'      => $r->start,
                'end'        => $r->end,
                'title'      => "Zajęte",
            ])
        );
    }

    public function adminCalendar(Request $request)
    {
        $validated = $request->validate([
            'view' => ['nullable', 'in:day,week,month'],
            'date' => ['nullable', 'date'],
        ]);

        $viewMode = $validated['view'] ?? 'day';
        $selectedDate = Carbon::parse($validated['date'] ?? now()->toDateString())->startOfDay();

        $shootingRange = auth()->user()
            ->shootingRanges()
            ->wherePivot('is_approved', true)
            ->with(['lanes', 'openingHours'])
            ->firstOrFail();

        if ($viewMode === 'month') {
            $periodStart = $selectedDate->copy()->startOfMonth()->startOfWeek();
            $periodEnd = $selectedDate->copy()->endOfMonth()->endOfWeek()->endOfDay();
        } elseif ($viewMode === 'week') {
            $periodStart = $selectedDate->copy()->startOfWeek();
            $periodEnd = $selectedDate->copy()->endOfWeek()->endOfDay();
        } else {
            $periodStart = $selectedDate->copy()->startOfDay();
            $periodEnd = $selectedDate->copy()->endOfDay();
        }

        $reservations = Reservation::query()
            ->where('shooting_range_id', $shootingRange->id)
            ->where('start', '<=', $periodEnd)
            ->where('end', '>=', $periodStart)
            ->with(['user:id,name,email,telephone', 'lane:id,name,length,numberOfPositions'])
            ->orderBy('start')
            ->get()
            ->map(function (Reservation $reservation) {
                return [
                    'id' => $reservation->id,
                    'user_name' => $reservation->user?->name ?? 'Brak danych',
                    'user_email' => $reservation->user?->email,
                    'user_phone' => $reservation->user?->telephone,
                    'lane_name' => $reservation->lane?->name ?? 'Usunięta oś',
                    'lane_length' => $reservation->lane?->length,
                    'start' => $reservation->start->format('Y-m-d H:i'),
                    'end' => $reservation->end->format('Y-m-d H:i'),
                    'date' => $reservation->start->format('Y-m-d'),
                    'start_time' => $reservation->start->format('H:i'),
                    'end_time' => $reservation->end->format('H:i'),
                    'positions_count' => $reservation->positions_count,
                    'status' => $reservation->status,
                ];
            })
            ->values();

        $openingHours = $shootingRange->openingHours
            ->mapWithKeys(function ($openingHour) {
                return [
                    (int) $openingHour->day_of_week => [
                        'opens_at' => Carbon::parse($openingHour->opens_at)->format('H:i'),
                        'closes_at' => Carbon::parse($openingHour->closes_at)->format('H:i'),
                    ],
                ];
            });

        return view('admin.reservations.calendar', [
            'shootingRange' => $shootingRange,
            'reservations' => $reservations,
            'openingHours' => $openingHours,
            'viewMode' => $viewMode,
            'selectedDate' => $selectedDate->format('Y-m-d'),
            'periodStart' => $periodStart->format('Y-m-d'),
            'periodEnd' => $periodEnd->format('Y-m-d'),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if (! auth()->check()) {
            return redirect()
                ->route('login')
                ->with('error', 'Musisz być zalogowany, aby dokonać rezerwacji.');
        }

        $validated = $request->validate([
            'shooting_range_id' => ['required', 'integer', 'exists:shooting_ranges,id'],
            'line_id' => ['required', 'integer', 'exists:lanes,id'],
            'date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'positions_count' => ['required', 'integer', 'min:1'],
        ]);

        $shootingRange = ShootingRange::query()
            ->where('is_visible', true)
            ->whereHas('employees', function ($query) {
                $query
                    ->where('shooting_range_employee.role', 'admin')
                    ->where('shooting_range_employee.is_approved', true)
                    ->where('users.is_approved', true);
            })
            ->with(['openingHours', 'holidays'])
            ->findOrFail($validated['shooting_range_id']);

        $lane = Lane::where('id', $validated['line_id'])
            ->where('shooting_range_id', $shootingRange->id)
            ->firstOrFail();

            if ((int) $validated['positions_count'] > $lane->numberOfPositions) {
                return back()->with('error', 'Wybrana liczba miejsc przekracza liczbę stanowisk na osi.');
            }

        $start = Carbon::parse($validated['date'] . ' ' . $validated['start_time']);
        $end = Carbon::parse($validated['date'] . ' ' . $validated['end_time']);
        $date = $start->copy()->startOfDay();

        $hasValidInterval = $shootingRange->intervals()
            ->where('is_active', true)
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->exists();

        if (! $hasValidInterval) {
            return back()->with('error', 'Strzelnica nie ma aktywnego lub ważnego przedziału pracy.');
        }

        $isHoliday = $shootingRange->holidays()
            ->whereDate('date', $date)
            ->exists();

        if ($isHoliday) {
            return back()->with('error', 'Strzelnica jest nieczynna w wybranym dniu.');
        }

        $openingHour = $shootingRange->openingHours()
            ->where('day_of_week', $start->isoWeekday())
            ->first();

        if (! $openingHour) {
            return back()->with('error', 'Strzelnica nie pracuje w wybranym dniu.');
        }

        $opensAt = Carbon::parse(
            $validated['date'] . ' ' . Carbon::parse($openingHour->opens_at)->format('H:i:s')
        );

        $closesAt = Carbon::parse(
            $validated['date'] . ' ' . Carbon::parse($openingHour->closes_at)->format('H:i:s')
        );

        if ($start->lt($opensAt) || $end->gt($closesAt)) {
            return back()->with('error', 'Godziny rezerwacji są poza godzinami pracy strzelnicy.');
        }

        $overlappingReservationsCount = Reservation::where('line_id', $lane->id)
            ->whereIn('status', ['pending', 'approved'])
            ->where('start', '<', $end)
            ->where('end', '>', $start)
            ->count();

        if ($overlappingReservationsCount >= $lane->numberOfPositions) {
            return back()->with('error', 'Brak wolnych miejsc na wybranej osi w tym terminie.');
        }

        $requestedPositions = (int) $validated['positions_count'];

        if ($requestedPositions > $lane->numberOfPositions) {
            return back()->with('error', 'Wybrana liczba miejsc przekracza liczbę stanowisk na osi.');
        }

        $slotStart = $start->copy();

        while ($slotStart->lt($end)) {
            $slotEnd = $slotStart->copy()->addMinutes(30);

            $reservedPositionsInSlot = Reservation::where('line_id', $lane->id)
                ->whereIn('status', ['pending', 'approved'])
                ->where('start', '<', $slotEnd)
                ->where('end', '>', $slotStart)
                ->sum('positions_count');

            if (($reservedPositionsInSlot + $requestedPositions) > $lane->numberOfPositions) {
                return back()->with(
                    'error',
                    'Brak wystarczającej liczby wolnych miejsc w przedziale ' .
                    $slotStart->format('H:i') . ' - ' . $slotEnd->format('H:i') . '.'
                );
            }

            $slotStart->addMinutes(30);
        }

        Reservation::create([
            'user_id' => auth()->id(),
            'mod_id' => auth()->id(),
            'shooting_range_id' => $shootingRange->id,
            'line_id' => $lane->id,
            'start' => $start,
            'end' => $end,
            'positions_count' => $validated['positions_count'],
            'status' => 'pending',
        ]);

        return back()->with('success', 'Rezerwacja została dodana.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}

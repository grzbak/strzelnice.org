<?php

namespace App\Http\Controllers;

use App\Models\Lane;
use App\Models\ShootingRange;
use App\Models\ShootingRangeHoliday;
use App\Models\ShootingRangeInterval;
use App\Models\ShootingRangeIntervalArchive;
use App\Models\ShootingRangeIntervalEvent;
use App\Models\ShootingRangeOpeningHour;
use App\Models\ShootingRangePackage;
use App\Models\User;
use App\Services\ShootingRangeAvailabilityService;
use App\Services\ShootingRangeSubscriptionService;
use App\Support\StructuredData;
use App\Support\Voivodeships;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ShootingsController extends Controller
{
    public function __construct(
        private readonly ShootingRangeAvailabilityService $availability,
        private readonly ShootingRangeSubscriptionService $subscriptions
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $messages = 'treść jako taka..';
        $shootings = collect();

        $searched = $request->hasAny([
            'date',
            'location',
            'location_mode',
            'lat',
            'lng',
            'radius_km',
            'lane_length',
            'viewport_radius_km',
        ]);

        if ($searched) {
            $validated = $request->validate([
                'date' => ['nullable', 'date', 'after_or_equal:today'],
                'location' => ['nullable', 'string', 'max:255'],
                'location_mode' => ['nullable', 'in:radius,voivodeship'],
                'lat' => ['nullable', 'numeric'],
                'lng' => ['nullable', 'numeric'],
                'radius_km' => ['nullable', 'numeric', 'min:0', 'max:500'],
                'lane_length' => ['nullable', 'integer', 'min:1'],
                'viewport_radius_km' => ['nullable', 'numeric', 'min:0'],
            ]);

            $voivodeships = [
                'dolnoslaskie' => 'dolnośląskie',
                'kujawsko-pomorskie' => 'kujawsko-pomorskie',
                'lubelskie' => 'lubelskie',
                'lubuskie' => 'lubuskie',
                'lodzkie' => 'łódzkie',
                'malopolskie' => 'małopolskie',
                'mazowieckie' => 'mazowieckie',
                'opolskie' => 'opolskie',
                'podkarpackie' => 'podkarpackie',
                'podlaskie' => 'podlaskie',
                'pomorskie' => 'pomorskie',
                'slaskie' => 'śląskie',
                'swietokrzyskie' => 'świętokrzyskie',
                'warminsko-mazurskie' => 'warmińsko-mazurskie',
                'wielkopolskie' => 'wielkopolskie',
                'zachodniopomorskie' => 'zachodniopomorskie',
            ];

            $location = trim($validated['location'] ?? '');
            $usesCurrentLocation = mb_strtolower($location) === 'moja lokalizacja';
            $voivodeshipKey = $this->detectVoivodeshipKey($location, $voivodeships);
            $isVoivodeshipSearch = $voivodeshipKey !== null;
            $locationMode = $validated['location_mode']
                ?? ($isVoivodeshipSearch ? 'voivodeship' : null);

            $date = Carbon::parse($validated['date'] ?? now()->format('Y-m-d'));
            $eligibilityDate = today();
            $dayOfWeek = $date->isoWeekday();
            $laneLength = (int) ($validated['lane_length'] ?? 0);

            $query = ShootingRange::query()
                ->where('is_visible', true)
                ->where('is_archived', false)
                ->whereHas('employees', function ($query) {
                    $query
                        ->where('shooting_range_employee.role', 'admin')
                        ->where('shooting_range_employee.is_approved', true)
                        ->where('users.is_approved', true);
                })
                ->whereHas('intervals', function ($q) use ($eligibilityDate) {
                    $q->where('is_active', true)
                        ->whereDate('start_date', '<=', $eligibilityDate)
                        ->whereDate('end_date', '>=', $eligibilityDate)
                        ->where(function ($q) use ($eligibilityDate) {
                            $q->whereNotNull('datePaid')
                                ->orWhere(function ($q) use ($eligibilityDate) {
                                    $q->whereNull('datePaid')
                                        ->whereDate(
                                            'payment_due_date',
                                            '>=',
                                            $eligibilityDate
                                        );
                                });
                        });
                })
                ->whereHas('openingHours', function ($q) use ($dayOfWeek) {
                    $q->where('day_of_week', $dayOfWeek);
                })
                ->whereDoesntHave('holidays', function ($q) use ($date) {
                    $q->whereDate('date', $date);
                })
                ->whereHas('lanes', function ($q) use ($laneLength) {
                    if ($laneLength > 0) {
                        $q->where('length', '>=', $laneLength);
                    }
                });

            if ($location && $locationMode === 'voivodeship') {
                if (! $voivodeshipKey) {
                    $query->whereRaw('1 = 0');
                } else {
                    $query->where('voivodeship', $voivodeships[$voivodeshipKey]);
                }
            } elseif (
                $location
                && $locationMode !== 'voivodeship'
                && $request->filled('radius_km')
                && $request->filled('lat')
                && $request->filled('lng')
            ) {
                $lat = (float) $validated['lat'];
                $lng = (float) $validated['lng'];
                $radius = (float) ($validated['radius_km'] ?? 0) + (float) ($validated['viewport_radius_km'] ?? 0);

                $query->select('shooting_ranges.*')
                    ->selectRaw(
                        '(6371 * acos(least(1, cos(radians(?)) * cos(radians(lat)) * cos(radians(lng) - radians(?)) + sin(radians(?)) * sin(radians(lat))))) AS distance',
                        [$lat, $lng, $lat]
                    )
                    ->having('distance', '<=', $radius)
                    ->orderBy('distance');
            } elseif (
                $location
                && $locationMode !== 'voivodeship'
                && $request->filled('radius_km')
                && ! $usesCurrentLocation
            ) {
                $coordinates = $this->geocodeLocation($location);

                if ($coordinates) {
                    $lat = $coordinates['lat'];
                    $lng = $coordinates['lng'];
                    $radius = (float) $validated['radius_km'];

                    $query->select('shooting_ranges.*')
                        ->selectRaw(
                            '(6371 * acos(least(1, cos(radians(?)) * cos(radians(lat)) * cos(radians(lng) - radians(?)) + sin(radians(?)) * sin(radians(lat))))) AS distance',
                            [$lat, $lng, $lat]
                        )
                        ->having('distance', '<=', $radius)
                        ->orderBy('distance');
                } else {
                    $query->whereRaw('1 = 0');
                }
            } elseif ($location && ! $usesCurrentLocation) {
                $query->where(function ($q) use ($location) {
                    $q->whereRaw('LOWER(town) LIKE ?', ['%'.mb_strtolower($location).'%'])
                        ->orWhereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower($location).'%'])
                        ->orWhereRaw('LOWER(street) LIKE ?', ['%'.mb_strtolower($location).'%']);
                });
            } elseif ($usesCurrentLocation) {
                $query->whereRaw('1 = 0');
            } else {
                $query->orderBy('name');
            }

            $user = $request->user();
            $shootingsCollection = $query
                ->with([
                    'openingHours',
                    'lanes' => function ($q) use ($laneLength) {
                        if ($laneLength > 0) {
                            $q->where('length', '>=', $laneLength);
                        }
                    },
                ])
                ->get()
                ->filter(function (ShootingRange $shootingRange) use ($date, $laneLength, $user) {
                    return $this->availability->availableForSearch(
                        $shootingRange,
                        $date,
                        $laneLength,
                        $user,
                        false
                    )['allowed'];
                })
                ->map(function (ShootingRange $shootingRange) use ($date) {
                    $shootingRange->available_lanes = $shootingRange->lanes
                        ->map(function (Lane $lane) use ($shootingRange, $date) {
                            $available = $lane->search_available_positions
                                ?? $this->availability
                                    ->maximumAvailablePositionsForDate(
                                        $lane,
                                        $shootingRange,
                                        $date
                                    );
                            $lane->reservations_count = max(
                                0,
                                (int) $lane->numberOfPositions - $available
                            );

                            return $lane;
                        })
                        ->filter(
                            fn (Lane $lane) => $lane->reservations_count
                                < $lane->numberOfPositions
                        )
                        ->values();

                    return $shootingRange;
                })
                ->filter(fn ($shootingRange) => $shootingRange->available_lanes->isNotEmpty())
                ->values();

            $page = LengthAwarePaginator::resolveCurrentPage();
            $shootings = new LengthAwarePaginator(
                $shootingsCollection->forPage($page, 10)->values(),
                $shootingsCollection->count(),
                10,
                $page,
                [
                    'path' => $request->url(),
                    'query' => $request->query(),
                ]
            );
        }

        $latestShootingRanges = $this->latestShootingRanges();

        return view('home', compact(
            'messages',
            'shootings',
            'searched',
            'latestShootingRanges'
        ));
    }

    private function latestShootingRanges()
    {
        $firstAdminAssignments = DB::table('shooting_range_employee')
            ->select('shooting_ranges_id', DB::raw('MIN(created_at) as first_admin_added_at'))
            ->where('role', 'admin')
            ->where('is_approved', true)
            ->groupBy('shooting_ranges_id');

        return ShootingRange::query()
            ->joinSub($firstAdminAssignments, 'first_admin_assignments', function ($join) {
                $join->on('shooting_ranges.id', '=', 'first_admin_assignments.shooting_ranges_id');
            })
            ->where('shooting_ranges.is_visible', true)
            ->where('shooting_ranges.is_archived', false)
            ->orderByDesc('first_admin_assignments.first_admin_added_at')
            ->limit(15)
            ->get([
                'shooting_ranges.id',
                'shooting_ranges.name',
                'shooting_ranges.town',
                'shooting_ranges.street',
                'shooting_ranges.houseNumber',
                'shooting_ranges.apartamentNumber',
                'shooting_ranges.zipCode',
                'shooting_ranges.brand_file_name',
                'shooting_ranges.endpoint',
                'shooting_ranges.created_at',
                'first_admin_assignments.first_admin_added_at',
            ]);
    }

    // pomocnicza
    private function normalizePolishLocation(string $value): string
    {
        return str($value)
            ->lower()
            ->trim()
            ->replace('województwo ', '')
            ->replace('wojewodztwo ', '')
            ->replace('ą', 'a')
            ->replace('ć', 'c')
            ->replace('ę', 'e')
            ->replace('ł', 'l')
            ->replace('ń', 'n')
            ->replace('ó', 'o')
            ->replace('ś', 's')
            ->replace('ż', 'z')
            ->replace('ź', 'z')
            ->toString();
    }

    // pomocnicza
    private function detectVoivodeshipKey(string $value, array $voivodeships): ?string
    {
        $normalized = $this->normalizePolishLocation($value);

        if (array_key_exists($normalized, $voivodeships)) {
            return $normalized;
        }

        foreach (array_keys($voivodeships) as $key) {
            if (str_contains($normalized, $key)) {
                return $key;
            }
        }

        return null;
    }

    private function geocodeLocation(string $location): ?array
    {
        $response = Http::get('https://maps.googleapis.com/maps/api/geocode/json', [
            'address' => $location,
            'components' => 'country:PL',
            'key' => config('services.google_maps.key'),
        ]);

        if (! $response->ok()) {
            return null;
        }

        $result = $response->json('results.0.geometry.location');

        if (! $result) {
            return null;
        }

        return [
            'lat' => $result['lat'],
            'lng' => $result['lng'],
        ];
    }

    public function maps()
    {
        $shootings = ShootingRange::where('is_visible', true)
            ->where('is_archived', false)
            ->get()
            ->filter(
                fn (ShootingRange $shootingRange) => $this->availability
                    ->canUserAccessRange(
                        $shootingRange,
                        auth()->user(),
                        today()
                    )
            )
            ->values();

        return view('maps.index', compact('shootings'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('shooting_ranges.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    public function publicShow(string $endpoint)
    {
        $today = Carbon::today();

        $shootingRange = ShootingRange::query()
            ->where('endpoint', $endpoint)
            ->where('is_visible', true)
            ->where('is_archived', false)
            ->with([
                'lanes',
                'openingHours',
                'holidays',
                'intervals',
                'employees',
                'invitations',
                'gallery.photos',
            ])
            ->firstOrFail();

        $availability = $this->availability->basicRequirements(
            $shootingRange,
            $today
        );
        $canReserve = $availability['allowed'];
        $reservationUnavailableReason = $availability['reason'] ?? '';

        $nearbyRanges = $this->nearbyShootingRanges($shootingRange);

        $voivodeshipSlug = Voivodeships::slugFor($shootingRange->voivodeship);
        $breadcrumbs = array_values(array_filter([
            ['name' => 'Strzelnice.org', 'url' => url('/')],
            ['name' => 'Lista strzelnic', 'url' => route('shootings.list')],
            $voivodeshipSlug ? [
                'name' => 'Województwo '.Voivodeships::name($voivodeshipSlug),
                'url' => route('shootings.voivodeship', ['voivodeship' => $voivodeshipSlug]),
            ] : null,
            ['name' => $shootingRange->name, 'url' => $shootingRange->public_link],
        ]));

        $seo = $shootingRange->seoData();
        $seo['schema'] = array_values(array_filter([
            StructuredData::shootingRange($shootingRange, $canReserve),
            StructuredData::breadcrumbs($breadcrumbs),
        ]));

        return view('shooting_ranges.public-show', compact(
            'shootingRange',
            'canReserve',
            'reservationUnavailableReason',
            'nearbyRanges',
            'breadcrumbs',
            'seo'
        ));
    }

    /**
     * Najbliższe strzelnice (linkowanie wewnętrzne na profilu).
     */
    private function nearbyShootingRanges(ShootingRange $shootingRange, int $limit = 6)
    {
        $candidates = ShootingRange::query()
            ->where('is_visible', true)
            ->where('is_archived', false)
            ->where('id', '!=', $shootingRange->id)
            ->whereNotNull('endpoint')
            ->get(['id', 'name', 'endpoint', 'town', 'voivodeship', 'lat', 'lng']);

        if ($shootingRange->lat && $shootingRange->lng) {
            return $candidates
                ->filter(fn (ShootingRange $range) => $range->lat && $range->lng)
                ->each(function (ShootingRange $range) use ($shootingRange) {
                    $range->distance_km = $this->distanceKm(
                        $shootingRange->lat,
                        $shootingRange->lng,
                        $range->lat,
                        $range->lng
                    );
                })
                ->sortBy('distance_km')
                ->take($limit)
                ->values();
        }

        return $candidates
            ->where('voivodeship', $shootingRange->voivodeship)
            ->take($limit)
            ->values();
    }

    private function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id) {}

    /**
     * Usuwanie osi strzelnicy.
     */
    public function destroyLane(string $id)
    {
        $user = auth()->user();
        $lane = Lane::query()
            ->where('is_archived', false)
            ->findOrFail($id);

        if (! $user->managesShootingRange($lane->shootingRange)) {
            abort(403); // brak dostępu
        }

        // zabezpieczenie: musi zostać min. 1 oś
        if ($lane->shootingRange->lanes()->count() <= 1) {
            return redirect()
                ->route('shooting.lanes.edit')
                ->with('error', 'Musi pozostać przynajmniej jedna oś!');
        }

        if ($lane->reservations()->exists()) {
            $lane->update(['is_archived' => true]);

            return redirect()
                ->route('shooting.lanes.edit')
                ->with(
                    'success',
                    'Oś posiada historię rezerwacji, dlatego została zarchiwizowana.'
                );
        }

        $lane->delete();

        return redirect()
            ->route('shooting.lanes.edit')
            ->with('success', 'Oś strzelecka została usunięta!');
    }

    // dodawanie strzelnicy przez gościa

    /* walidacja zachodzenia się na siebie zakresów dat
if (ShootingRangeInterval::hasOverlap(
    $request->start_date,
    $request->end_date,
    $request->shooting_range_id
)) {
    return back()->withErrors('Zakres dat nachodzi na istniejący!');
}
*/

    // ==================================================================================
    // ROLE: SUPERADMIN
    // ==================================================================================

    // LISTA STRZELNIC W PANELU SUPERADMINA
    public function listSuperAdmin(Request $request)
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', 'in:name,town,voivodeship,created_at'],
            'direction' => ['nullable', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'in:10,20,50'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $sort = $validated['sort'] ?? 'name';
        $direction = $validated['direction'] ?? 'asc';
        $perPage = (int) ($validated['per_page'] ?? 20);

        $query = ShootingRange::query()
            ->where('is_archived', false)
            ->with('activeInterval')
            ->withCount('reservations')
            ->withCount([
                'employees as active_admins_count' => fn ($query) => $query
                    ->where('shooting_range_employee.role', 'admin')
                    ->where('shooting_range_employee.is_approved', true)
                    ->where('users.is_approved', true),
            ]);

        foreach (preg_split('/\s+/u', $search, -1, PREG_SPLIT_NO_EMPTY) as $term) {
            $query->where(function ($query) use ($term) {
                $query
                    ->where('name', 'like', "%{$term}%")
                    ->orWhere('town', 'like', "%{$term}%")
                    ->orWhere('voivodeship', 'like', "%{$term}%")
                    ->orWhere('street', 'like', "%{$term}%")
                    ->orWhere('houseNumber', 'like', "%{$term}%")
                    ->orWhere('zipCode', 'like', "%{$term}%");
            });
        }

        match ($sort) {
            'town' => $query->orderBy('town', $direction)->orderBy('name'),
            'voivodeship' => $query->orderBy('voivodeship', $direction)->orderBy('name'),
            'created_at' => $query->orderBy('created_at', $direction)->orderBy('name'),
            default => $query->orderBy('name', $direction),
        };

        $shootings = $query->paginate($perPage)->withQueryString();

        return view('superAdmin.shooting_ranges.index', compact(
            'shootings',
            'search',
            'sort',
            'direction',
            'perPage'
        ));
    }

    // IMPORT STRZELNIC Z EXCELA
    public function import(Request $request)
    {
        $request->validate([
            'file' => [
                'required',
                'file',
                'mimes:xls,xlsx',
            ],
        ]);

        $spreadsheet = IOFactory::load(
            $request->file('file')->getRealPath()
        );

        $rows = $spreadsheet
            ->getActiveSheet()
            ->toArray();

        $headers = array_map('trim', $rows[0]);

        unset($rows[0]);

        $imported = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            $data = array_combine($headers, $row);

            $mapped = [
                'name' => $this->importValue($data, 'Nazwa *', 'Nazwa'),
                'voivodeship' => $this->importValue($data, 'Województwo *', 'Województwo'),
                'town' => $this->importValue($data, 'Miejscowość *', 'Miejscowość'),
                'street' => $this->importValue($data, 'Ulica', 'Ulica *'),
                'houseNumber' => $this->importValue($data, 'Nr domu', 'Nr domu *'),
                'apartamentNumber' => $this->importValue($data, 'Nr lokalu'),
                'plotNumber' => $this->importValue($data, 'Nr działki'),
                'zipCode' => $this->importValue($data, 'Kod pocztowy *', 'Kod pocztowy'),
                'navLink' => $this->importValue($data, 'Link nawigacji *', 'Link nawigacji'),
                'lat' => $this->importValue($data, 'Szerokość geogr. LAT *', 'Szerokość geogr. LAT'),
                'lng' => $this->importValue($data, 'Długość geogr. LNG *', 'Długość geogr. LNG'),
                'email' => $this->importValue($data, 'Email', 'Email *'),
                'url' => $this->importValue($data, 'Strona www'),
                'facebook' => $this->importValue($data, 'Facebook'),
                'instagram' => $this->importValue($data, 'Instagram'),
                'telephone1' => $this->importValue($data, 'Telefon 1', 'Telefon 1 *'),
                'telephone2' => $this->importValue($data, 'Telefon 2'),
                'type' => $this->importValue($data, 'Typ *', 'Typ'),
                'description' => $this->importValue($data, 'Opis'),
                'is_visible' => 1,
            ];

            $validator = Validator::make($mapped, [
                'name' => ['required', 'string', 'max:255'],
                'voivodeship' => ['required', 'string', 'max:255'],
                'town' => ['required', 'string', 'max:255'],
                'street' => ['nullable', 'string', 'max:255'],
                'houseNumber' => ['nullable', 'string', 'max:255'],
                'zipCode' => ['required', 'regex:/^\d{2}-\d{3}$/'],
                'navLink' => ['required', 'string'],
                'lat' => ['required', 'numeric', 'between:-90,90'],
                'lng' => ['required', 'numeric', 'between:-180,180'],
                'email' => ['nullable', 'email'],
                'url' => ['nullable', 'url'],
                'telephone1' => ['nullable', 'string', 'max:255'],
                'type' => ['required', 'in:otwarta,kryta'],
                'description' => ['nullable', 'string'],
            ]);

            if ($validator->fails()) {
                $errors[] = 'Wiersz '.($index + 1).': '.implode(', ', $validator->errors()->all());

                continue;
            }

            // <sprawdzanie duplikatów
            if (! empty($mapped['email'])) {
                $existingByEmail = ShootingRange::whereRaw('LOWER(email) = ?', [
                    mb_strtolower($mapped['email']),
                ])->first();

                if ($existingByEmail) {
                    $errors[] = 'Wiersz '.($index + 1).': pominięto — istnieje już strzelnica z tym samym adresem email: '.$mapped['email'];

                    continue;
                }
            }

            $sameAddressRanges = ShootingRange::where('town', $mapped['town'])
                ->where('street', $mapped['street'])
                ->where('houseNumber', $mapped['houseNumber'])
                ->where('zipCode', $mapped['zipCode'])
                ->get();

            foreach ($sameAddressRanges as $existingRange) {
                if ($this->isSimilarName($mapped['name'], $existingRange->name)) {
                    $errors[] = 'Wiersz '.($index + 1).': pominięto — prawdopodobny duplikat: podobna nazwa + ten sam adres. Istnieje: '.$existingRange->name;

                    continue 2;
                }
            }
            // </sprawdzanie duplikatów

            ShootingRange::create($mapped);
            $imported++;
        }

        $response = back()
            ->with('import_errors', $errors);

        if ($imported > 0) {
            $response->with(
                'import_success',
                "Zaimportowano strzelnice: {$imported}"
            );
        }

        return $response;
    }

    private function importValue(array $data, string ...$headers): mixed
    {
        foreach ($headers as $header) {
            if (! array_key_exists($header, $data)) {
                continue;
            }

            $value = $data[$header];

            if (is_string($value)) {
                $value = trim($value);
            }

            return $value === '' ? null : $value;
        }

        return null;
    }

    // FORMULARZ DODAWANIA STRZELNICY PRZEZ SUPERADMINA
    public function createSuperAdmin()
    {
        return view('superAdmin.shooting_ranges.create');
    }

    // ZAPIS NOWEJ STRZELNICY PRZEZ SUPERADMINA
    public function storeSuperAdmin(Request $request)
    {
        $validated = $request->validate(
            $this->superAdminShootingRangeStoreRules(),
            $this->shootingRangeValidationMessages()
        );

        unset($validated['brand']);

        $validated['is_public_access'] = $request->boolean('is_public_access');
        $validated['requires_reservation_confirmation'] = $request->boolean(
            'requires_reservation_confirmation',
            true
        );
        $validated['requires_shooting_leader'] = $request->boolean(
            'requires_shooting_leader'
        );

        if ($brandData = $this->storeBrandFile($request)) {
            $validated = array_merge($validated, $brandData);
        }

        $shootingRange = ShootingRange::create($validated);

        return redirect()
            ->route('admin.shootings.edit', $shootingRange)
            ->with('success', 'Strzelnica została dodana.');
    }

    // FORMULARZ EDYCJI STRZELNICY PRZEZ SUPERADMINA
    public function editSuperAdmin(ShootingRange $shootingRange)
    {
        return view(
            'superAdmin.shooting_ranges.edit',
            compact('shootingRange')
        );
    }

    // ZAPIS ZMIAN PRZEZ SUPERADMINA
    public function updateSuperAdmin(Request $request, ShootingRange $shootingRange)
    {
        $rules = $this->shootingRangeRules();
        $rules['remove_brand'] = ['nullable', 'boolean'];

        $validated = $request->validate(
            $rules,
            $this->shootingRangeValidationMessages()
        );

        unset($validated['brand'], $validated['remove_brand']);

        $validated['is_public_access'] = $request->boolean('is_public_access');
        $validated['requires_reservation_confirmation'] = $request->boolean(
            'requires_reservation_confirmation'
        );
        $validated['requires_shooting_leader'] = $request->boolean(
            'requires_shooting_leader'
        );

        if ($brandData = $this->storeBrandFile($request)) {
            $this->deleteBrandFile($shootingRange);

            $validated = array_merge($validated, $brandData);
        } elseif ($request->boolean('remove_brand')) {
            $this->deleteBrandFile($shootingRange);

            $validated['brand_original_name'] = null;
            $validated['brand_file_name'] = null;
        }

        $shootingRange->update($validated);

        return redirect()
            ->route('admin.shootings.edit', $shootingRange)
            ->with('success', 'Dane strzelnicy zostały zaktualizowane.');
    }

    // PAKIETY STRZELNICY W PANELU SUPERADMINA
    public function packages($id)
    {
        $shooting = ShootingRange::where('id', $id)->firstOrFail();

        return view('superAdmin.shooting_ranges.index', compact('shootings'));
    }

    // ZMIANA STATUSU WIDOCZNOŚCI STRZELNICY NA STRONIE
    public function toggleVisibility(ShootingRange $shootingRange)
    {
        if ($shootingRange->is_archived) {
            return back()->with('error', 'Nie można zmienić widoczności zarchiwizowanej strzelnicy.');
        }

        $shootingRange->update([
            'is_visible' => ! $shootingRange->is_visible,
        ]);

        return back()->with('success', 'Zmieniono status widoczności strzelnicy.');
    }

    public function destroyShootingRange(Request $request, ShootingRange $shootingRange)
    {
        return $this->removeShootingRanges(
            collect([$shootingRange->id]),
            $request->boolean('archive_with_reservations')
        );
    }

    public function bulkDestroyShootingRanges(Request $request)
    {
        $validated = $request->validate([
            'shooting_range_ids' => ['required', 'array', 'min:1'],
            'shooting_range_ids.*' => ['integer', 'exists:shooting_ranges,id'],
            'archive_with_reservations' => ['nullable', 'boolean'],
        ]);

        return $this->removeShootingRanges(
            collect($validated['shooting_range_ids'])->unique()->values(),
            $request->boolean('archive_with_reservations')
        );
    }

    private function removeShootingRanges($ids, bool $archiveWithReservations)
    {
        $ranges = ShootingRange::query()
            ->whereIn('id', $ids)
            ->where('is_archived', false)
            ->with(['gallery.photos'])
            ->withCount('reservations')
            ->get();

        if ($ranges->isEmpty()) {
            return back()->with('error', 'Nie wybrano aktywnych strzelnic do usunięcia.');
        }

        $deleted = 0;
        $archived = 0;
        $blocked = [];

        foreach ($ranges as $range) {
            if ($range->reservations_count > 0) {
                if (! $archiveWithReservations) {
                    $blocked[] = $range->name;

                    continue;
                }

                $range->update([
                    'is_archived' => true,
                    'is_visible' => false,
                ]);

                $archived++;

                continue;
            }

            $this->deleteShootingRangeWithoutReservations($range);
            $deleted++;
        }

        if ($blocked) {
            return back()->with(
                'error',
                'Nie usunięto strzelnic z rezerwacjami: '.implode(', ', $blocked).'. Potwierdź archiwizację, aby ustawić je jako archiwalne.'
            );
        }

        $messages = [];

        if ($deleted > 0) {
            $messages[] = $deleted === 1
                ? 'Usunięto 1 strzelnicę wraz z przedziałami, galerią, zdjęciami, godzinami pracy, dniami wolnymi i zaproszeniami.'
                : 'Usunięto '.$deleted.' strzelnic wraz z przedziałami, galeriami, zdjęciami, godzinami pracy, dniami wolnymi i zaproszeniami.';
        }

        if ($archived > 0) {
            $messages[] = $archived === 1
                ? 'Zarchiwizowano 1 strzelnicę z istniejącymi rezerwacjami.'
                : 'Zarchiwizowano '.$archived.' strzelnic z istniejącymi rezerwacjami.';
        }

        return back()->with('success', implode(' ', $messages));
    }

    private function deleteShootingRangeWithoutReservations(ShootingRange $shootingRange): void
    {
        $galleryFolder = $shootingRange->gallery?->folder_path;

        DB::transaction(function () use ($shootingRange) {
            $shootingRange->intervalArchives()->delete();
            $shootingRange->intervals()->delete();
            $shootingRange->openingHours()->delete();
            $shootingRange->holidays()->delete();
            $shootingRange->invitations()->delete();
            $shootingRange->packages()->where('shooting_range_id', $shootingRange->id)->delete();
            $shootingRange->employees()->detach();
            $shootingRange->lanes()->delete();
            $shootingRange->gallery?->delete();
            $shootingRange->delete();
        });

        if ($galleryFolder) {
            File::deleteDirectory(public_path($galleryFolder));
        }
    }

    // ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    // ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    // ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    // ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    public function intervals(ShootingRange $shootingRange)
    {
        $today = Carbon::today();

        $latestActiveInterval = $shootingRange->intervals()
            ->where('is_active', true)
            ->whereDate('end_date', '>=', $today)
            ->orderByDesc('end_date')
            ->first();

        $suggestedStartDate = $latestActiveInterval
            ? $latestActiveInterval->end_date->copy()->addDay()->format('Y-m-d')
            : $today->format('Y-m-d');

        $suggestedEndDate = Carbon::parse($suggestedStartDate)
            ->addMonthNoOverflow()
            ->subDay()
            ->format('Y-m-d');

        $intervals = $shootingRange->intervals()
            ->with(['packageOrders.package', 'packageOrders.payments', 'events.packageOrder', 'events.payment', 'events.createdBy'])
            ->orderByDesc('start_date')
            ->get();

        $archiveIntervals = $shootingRange->intervalArchives()
            ->orderByDesc('start_date')
            ->get();

        return view('superAdmin.shooting_ranges.intervals.index', compact(
            'shootingRange',
            'intervals',
            'archiveIntervals',
            'suggestedStartDate',
            'suggestedEndDate'
        ));
    }

    private function activePackagesForShootingRange(int $shootingRangeId)
    {
        $today = now()->toDateString();

        return ShootingRangePackage::query()
            ->where('shooting_range_id', $shootingRangeId)
            ->where('is_active', true)
            ->whereDate('valid_from', '<=', $today)
            ->where(function ($query) use ($today) {
                $query->whereNull('valid_to')
                    ->orWhereDate('valid_to', '>=', $today);
            })
            ->orderByDesc('valid_from')
            ->orderByDesc('id')
            ->get()
            ->groupBy('period_months')
            ->map(fn ($packages) => $packages->first())
            ->values();
    }

    public function storeInterval(Request $request, ShootingRange $shootingRange)
    {
        $validated = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'manual_reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $startDate = Carbon::parse($validated['start_date']);
        $endDate = Carbon::parse($validated['end_date']);

        $this->subscriptions->extendManualAccess(
            $shootingRange,
            $startDate,
            $endDate,
            $request->user(),
            $validated['manual_reason'] ?? null
        );

        return back()->with('success', 'Rozszerzono ręczny przedział aktywności.');
    }

    public function editInterval(ShootingRange $shootingRange, ShootingRangeInterval $interval)
    {
        abort_if($interval->shooting_range_id !== $shootingRange->id, 404);

        return view('superAdmin.shooting_ranges.intervals.edit', compact('shootingRange', 'interval'));
    }

    public function updateInterval(Request $request, ShootingRange $shootingRange, ShootingRangeInterval $interval)
    {
        abort_if($interval->shooting_range_id !== $shootingRange->id, 404);

        $validated = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'is_active' => ['nullable', 'boolean'],
            'manual_reason' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($interval->hasBlockingPackageOrders()) {
            return back()->with('error', 'Nie można edytować ręcznie przedziału powiązanego z abonamentami. Zarządzaj nim przez zamówienia abonamentów.');
        }

        $previousStartDate = $interval->start_date?->copy();
        $previousEndDate = $interval->end_date?->copy();
        $isActive = $request->boolean('is_active');

        $interval->update([
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'is_active' => $isActive,
            'source' => 'manual',
            'subscription_status' => $isActive ? 'active' : 'expired',
            'datePaid' => $isActive ? ($interval->datePaid ?? now()) : $interval->datePaid,
            'payment_due_date' => $isActive ? null : $interval->payment_due_date,
        ]);

        ShootingRangeIntervalEvent::create([
            'shooting_range_interval_id' => $interval->id,
            'shooting_range_id' => $interval->shooting_range_id,
            'created_by_user_id' => $request->user()?->id,
            'type' => ShootingRangeIntervalEvent::TYPE_MANUAL_EXTENDED,
            'source' => ShootingRangeIntervalEvent::SOURCE_MANUAL,
            'date_from' => $interval->start_date,
            'date_to' => $interval->end_date,
            'previous_start_date' => $previousStartDate,
            'previous_end_date' => $previousEndDate,
            'new_start_date' => $interval->start_date,
            'new_end_date' => $interval->end_date,
            'note' => $validated['manual_reason'] ?? null,
        ]);

        ShootingRangeIntervalArchive::updateOrCreate(
            [
                'shooting_range_interval_id' => $interval->id,
            ],
            [
                'shooting_range_id' => $interval->shooting_range_id,
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

        return redirect()
            ->route('admin.shootings.intervals', $shootingRange)
            ->with('success', 'Zaktualizowano przedział.');
    }

    public function deleteInterval(ShootingRange $shootingRange, ShootingRangeInterval $interval)
    {
        abort_if($interval->shooting_range_id !== $shootingRange->id, 404);

        if ($interval->hasBlockingPackageOrders()) {
            return back()->with('error', 'Nie można usunąć przedziału powiązanego z abonamentami. Zarządzaj nim przez zamówienia abonamentów.');
        }

        ShootingRangeIntervalArchive::where('shooting_range_interval_id', $interval->id)
            ->update([
                'operator_update_id' => auth()->id(),
                'is_active' => 0,
            ]);

        $interval->delete();

        return back()->with('success', 'Usunięto przedział z aktywnych.');
    }
    // ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    // ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    // ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    // ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    // ==================================================================================
    // ROLE: ADMINISTRATOR
    // ==================================================================================

    // Edycja danych podstawowych strzelnicy(formularz). Dostęp dla pracownika/administratora strzelnicy
    public function edit()
    {
        /** @var User $user */
        $user = Auth::user();
        $shootingRange = $user->shootingRanges()
            // ->wherePivot('role', 'admin')
            ->wherePivot('is_approved', true)
            ->first();

        return view('admin.shooting_ranges.edit', compact('shootingRange'));
    }

    // Zapis danych podstawowych strzelnicy(formularz). Dostęp dla pracownika/administratora strzelnicy
    public function update(Request $request, ShootingRange $shootingRange)
    {
        $rules = $this->shootingRangeRules();
        $rules['remove_brand'] = ['nullable', 'boolean'];

        $validated = $request->validate(
            $rules,
            $this->shootingRangeValidationMessages()
        );

        unset($validated['brand'], $validated['remove_brand']);

        $validated['is_public_access'] = $request->boolean('is_public_access');
        $validated['requires_reservation_confirmation'] = $request->boolean(
            'requires_reservation_confirmation'
        );
        $validated['requires_shooting_leader'] = $request->boolean(
            'requires_shooting_leader'
        );

        if ($brandData = $this->storeBrandFile($request)) {
            $this->deleteBrandFile($shootingRange);

            $validated = array_merge($validated, $brandData);
        } elseif ($request->boolean('remove_brand')) {
            $this->deleteBrandFile($shootingRange);

            $validated['brand_original_name'] = null;
            $validated['brand_file_name'] = null;
        }

        $shootingRange->update($validated);

        return redirect()->route('shooting.edit', ['id' => $shootingRange->id])
            ->with('success', 'Dane strzelnicy zostały zapisane.');
    }
    // ===============================================================================================================================================

    // Edycja godzin otwarcia strzelnicy(formularz). Dostęp dla pracownika/administratora strzelnicy
    public function editTime(Request $request)
    {
        $year = $request->input('year', now()->year);
        /** @var User $user */
        $user = Auth::user();
        $shootingRange = $user->shootingRanges()
            // ->wherePivot('role', 'admin')
            ->wherePivot('is_approved', true)
            ->first();
        $openingHours = ShootingRangeOpeningHour::whereShootingRangeId($shootingRange->id)->get();
        $holidays = $shootingRange->holidays()->whereYear('date', $year)->orderBy('date')->get();

        // dd($openingHours);
        return view('admin.shooting_ranges.openingTimes', compact('shootingRange', 'openingHours', 'holidays'));
    }
    // ===============================================================================================================================================

    // Zapis godzin otwarcia strzelnicy(formularz). Dostęp dla pracownika/administratora strzelnicy
    public function updateTime(Request $request, ShootingRange $shootingRange)
    {
        $validated = $request->validate([
            'days' => ['required', 'array'],

            'days.*.opens_at' => ['nullable', 'date_format:H:i'],
            'days.*.closes_at' => ['nullable', 'date_format:H:i'],
            'days.*.closed' => ['nullable'],
        ]);

        // usuń stare godziny
        $shootingRange->openingHours()->delete();

        foreach ($request->days as $day => $data) {

            // pomiń dzień oznaczony jako nieczynny
            if (isset($data['closed'])) {
                continue;
            }

            // pomiń puste
            if (empty($data['opens_at']) || empty($data['closes_at'])) {
                continue;
            }

            $shootingRange->openingHours()->create([
                'day_of_week' => $day + 1,
                'opens_at' => $data['opens_at'],
                'closes_at' => $data['closes_at'],
            ]);
        }

        return redirect()
            ->back()
            ->with('success', 'Godziny pracy zostały zapisane.');
    }
    // ===============================================================================================================================================

    // Dodawanie wpisu zawierającego dzień wolny
    public function addHoliday(Request $request, ShootingRange $shootingRange)
    {
        $validated = $request->validate([
            'date' => ['required', 'date'],
            'name' => ['required', 'string', 'max:255', 'min:3'],
            'holidayDescription' => ['nullable', 'string'],
        ]);

        $shootingRange->holidays()->create($validated);

        return back()->with('success', 'Dodano dzień wolny.');
    }
    // ===============================================================================================================================================

    // Usuwanie wpisu zawierającego dzień wolny
    public function deleteHoliday(
        ShootingRange $shootingRange,
        ShootingRangeHoliday $holiday
    ) {

        if ($holiday->shooting_range_id !== $shootingRange->id) {
            abort(403);
        }

        $holiday->delete();

        return back()->with('success', 'Usunięto dzień wolny.');
    }
    // ===============================================================================================================================================

    // Edycja osi strzelnicy(formularz). Dostęp dla pracownika/administratora strzelnicy
    public function editLanes()
    {
        /** @var User $user */
        $user = Auth::user();
        $shootingRange = $user->shootingRanges()
            // ->wherePivot('role', 'admin')
            ->wherePivot('is_approved', true)
            ->first();

        return view('admin.shooting_ranges.lanes', compact('shootingRange'));
    }

    // Zapis nowej osi strzelnicy(formularz). Dostęp dla pracownika/administratora strzelnicy
    public function updateLane(Request $request, $id)
    {
        $validated = $request->validate(
            [
                'name' => ['required', 'string', 'min:3', 'max:255'],
                'length' => ['required', 'integer', 'min:1'],
                'numberOfPositions' => ['required', 'integer', 'min:1'],
                'allows_dynamic_shooting' => ['required', 'boolean'],
                'description' => ['nullable', 'string'],
            ],
            [
                'name.required' => 'Nazwa osi jest wymagana.',
                'name.min' => 'Nazwa osi musi mieć co najmniej :min znak.',
                'name.max' => 'Nazwa osi może mieć maksymalnie :max znaków.',
            ]
        );
        /** @var User $user */
        $user = Auth::user();
        $shootingRange = $user->shootingRanges()
            // ->wherePivot('role', 'admin')
            ->wherePivot('is_approved', true)
            ->first();

        $shootingRange->lanes()->where('id', $id)->update($validated);

        return redirect()->route('shooting.lanes.edit', ['id' => $shootingRange->id])
            ->with('success', 'Oś została zaktualizowana.');
    }

    // Zapis nowej osi strzelnicy(formularz). Dostęp dla pracownika/administratora strzelnicy
    public function storeLane(Request $request)
    {
        $validated = $request->validate(
            [
                'name' => ['required', 'string', 'min:3', 'max:255'],
                'length' => ['required', 'integer', 'min:1'],
                'numberOfPositions' => ['required', 'integer', 'min:1'],
                'allows_dynamic_shooting' => ['required', 'boolean'],
                'description' => ['nullable', 'string'],
            ],
            [
                'name.required' => 'Nazwa osi jest wymagana.',
                'name.min' => 'Nazwa osi musi mieć co najmniej :min znak.',
                'name.max' => 'Nazwa osi może mieć maksymalnie :max znaków.',
            ]
        );
        /** @var User $user */
        $user = Auth::user();
        $shootingRange = $user->shootingRanges()
            // ->wherePivot('role', 'admin')
            ->wherePivot('is_approved', true)
            ->first();

        $shootingRange->lanes()->create($validated);

        return redirect()->route('shooting.lanes.edit', ['id' => $shootingRange->id])
            ->with('success', 'Oś została dodana');
    }

    // ==================================================================================
    // ROLE: GOŚĆ
    // ==================================================================================

    // Dodawanie nowej strzelnicy + tworzenie konta administratora (3 kroki)
    public function step1()
    {
        $url = parse_url(url()->previous(), PHP_URL_PATH);
        if (($url == '/strzelnica/dodaj/krok-1') || ($url == '/strzelnica/dodaj/krok-2') || ($url == '/strzelnica/dodaj/krok-3')) {

            return view('shooting_ranges.step1', [
                'step' => 1,
            ]);
        }

        if (session()->has('shooting_range_form')) {
            return view('shooting_ranges.resume');
        }

        // start nowego formularza

        return view('shooting_ranges.step1', [
            'step' => 1,
        ]);
    }

    public function postStep1(Request $request)
    {
        session()->put(
            'shooting_range_form',
            session('shooting_range_form') ?? [
                'step' => 1,
                'range' => [],
                'lanes' => [],
                'admin' => [],
            ]
        );

        $validated = $request->validate(
            $this->shootingRangeRules(),
            $this->shootingRangeValidationMessages()
        );

        unset($validated['brand']);

        $validated['is_public_access'] = $request->boolean('is_public_access');
        $validated['requires_reservation_confirmation'] = $request->boolean(
            'requires_reservation_confirmation',
            true
        );
        $validated['requires_shooting_leader'] = $request->boolean(
            'requires_shooting_leader'
        );

        if ($brandData = $this->storeBrandFile($request)) {
            $oldBrand = session('shooting_range_form.range.brand_file_name');

            if ($oldBrand) {
                $path = public_path('files/brands/'.$oldBrand);

                if (file_exists($path)) {
                    unlink($path);
                }
            }

            $validated = array_merge($validated, $brandData);
        }

        session()->put('shooting_range_form.range', $validated);
        session()->put('shooting_range_form.step', 2);

        return redirect()->route('range.step2');
    }

    public function step2()
    {
        $step = 2;

        return view('shooting_ranges.step2', [
            'step' => $step,
            'data' => session('shooting_range_form.lanes', []),
        ]);
    }

    public function postStep2(Request $request)
    {
        $validated = $request->validate(
            [
                'lanes' => ['required', 'array', 'min:1'],

                'lanes.*.name' => ['required', 'string', 'min:1', 'max:255'],
                'lanes.*.length' => ['required', 'integer', 'min:1'],
                'lanes.*.numberOfPositions' => ['required', 'integer', 'min:1'],
                'lanes.*.allows_dynamic_shooting' => ['required', 'boolean'],
                'lanes.*.description' => ['nullable', 'string'],
            ],
            [
                'lanes.*.name.required' => 'Nazwa osi jest wymagana.',
                'lanes.*.name.min' => 'Nazwa osi musi mieć co najmniej :min znak.',
                'lanes.*.name.max' => 'Nazwa osi może mieć maksymalnie :max znaków.',
            ]
        );
        session()->put('shooting_range_form.lanes', $validated['lanes']);
        session()->put('shooting_range_form.step', 3);

        return redirect()->route('range.step3');
    }

    public function step3()
    {
        $step = 3;

        return view('shooting_ranges.step3', [
            'step' => $step,
            'admin' => session('shooting_range_form.admin', []),
        ]);
    }

    public function storeGuest(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'telephone' => ['nullable', 'string'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        session()->put('shooting_range_form.admin', $validated);

        // dd(session('shooting_range_form'));
        $rangeData = session()->get('shooting_range_form.range', []);
        // dd($rangeData);
        $lanesData = session()->get('shooting_range_form.lanes', []);
        $adminData = session()->get('shooting_range_form.admin', []);

        DB::transaction(function () use ($rangeData, $lanesData, $adminData) {
            // tworzymy strzelnicę
            $range = ShootingRange::create($rangeData);

            // tworzymy osie
            foreach ($lanesData as $lane) {
                $range->lanes()->create($lane);
            }
            // tworzymy użytkownika admina
            $adminDataForCreate = [
                'name' => $adminData['name'],
                'email' => $adminData['email'],
                'telephone' => $adminData['telephone'],
                'password' => bcrypt($adminData['password']),
                'role' => 'admin',
                'is_approved' => true,
            ];

            if (Schema::hasColumn('users', 'approval_status')) {
                $adminDataForCreate += [
                    'approval_status' => User::APPROVAL_APPROVED,
                    'approved_at' => now(),
                ];
            }

            $admin = User::create($adminDataForCreate);

            // przypisanie strzelnicy do admina (jeśli jest relacja wiele-do-wielu)
            $admin->shootingRanges()->syncWithoutDetaching([
                $range->id => ['role' => 'admin'],
            ]);
        });

        session()->forget('shooting_range_form');

        return redirect()->route('maps');
    }

    // funkcje pomocnicze
    private function shootingRangeRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],

            'voivodeship' => ['required', 'string', 'max:100'],
            'town' => ['required', 'string', 'max:150'],
            'street' => ['nullable', 'string', 'max:150'],
            'houseNumber' => ['nullable', 'string', 'max:20'],
            'apartamentNumber' => ['nullable', 'string', 'max:20'],
            'plotNumber' => ['nullable', 'string', 'max:100'],
            'zipCode' => ['nullable', 'string', 'regex:/^\d{2}-\d{3}$/'],

            'navLink' => ['required', 'string', 'max:2000'],
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],

            'email' => ['nullable', 'email', 'max:255'],
            'url' => ['nullable', 'url', 'max:2000'],
            'facebook' => ['nullable', 'url', 'max:2000'],
            'instagram' => ['nullable', 'url', 'max:2000'],

            'telephone1' => ['nullable', 'string', 'max:30'],
            'telephone2' => ['nullable', 'string', 'max:30'],
            'brand' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:300'],

            'type' => ['required', 'in:otwarta,kryta'],
            'is_public_access' => ['required', 'boolean'],
            'max_advance_booking_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'requires_reservation_confirmation' => ['nullable', 'boolean'],
            'requires_shooting_leader' => ['nullable', 'boolean'],

            'description' => ['nullable', 'string', 'max:2000'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'meta_keywords' => ['nullable', 'string', 'max:500'],
            'og_title' => ['nullable', 'string', 'max:255'],
            'og_description' => ['nullable', 'string', 'max:500'],
            'og_image' => ['nullable', 'string', 'max:255'],
            'robots' => [
                'nullable',
                'string',
                'max:50',
                Rule::in([
                    'index,follow',
                    'noindex,follow',
                    'index,nofollow',
                    'noindex,nofollow',
                ]),
            ],
        ];
    }

    private function superAdminShootingRangeStoreRules(): array
    {
        $rules = $this->shootingRangeRules();

        $rules['street'] = ['nullable', 'string', 'max:150'];
        $rules['houseNumber'] = ['nullable', 'string', 'max:20'];
        $rules['apartamentNumber'] = ['nullable', 'string', 'max:20'];
        $rules['plotNumber'] = ['nullable', 'string', 'max:100'];
        $rules['zipCode'] = ['nullable', 'string', 'regex:/^\d{2}-\d{3}$/'];
        $rules['email'] = ['nullable', 'email', 'max:255'];
        $rules['url'] = ['nullable', 'url', 'max:2000'];
        $rules['facebook'] = ['nullable', 'url', 'max:2000'];
        $rules['instagram'] = ['nullable', 'url', 'max:2000'];
        $rules['telephone1'] = ['nullable', 'string', 'max:30'];
        $rules['telephone2'] = ['nullable', 'string', 'max:30'];

        return $rules;
    }

    private function shootingRangeValidationMessages(): array
    {
        return [
            'zipCode.regex' => 'Kod pocztowy musi mieć format 00-000.',
            'url.url' => 'Adres WWW musi zaczynać się od http:// lub https://.',
            'facebook.url' => 'Adres Facebook musi zaczynać się od http:// lub https://.',
            'instagram.url' => 'Adres Instagram musi zaczynać się od http:// lub https://.',
        ];
    }

    private function normalizeText(?string $value): string
    {
        $value = mb_strtolower(trim((string) $value));
        $value = preg_replace('/\s+/', ' ', $value);

        return $value;
    }

    private function isSimilarName(string $name1, string $name2): bool
    {
        similar_text(
            $this->normalizeText($name1),
            $this->normalizeText($name2),
            $percent
        );

        return $percent >= 80;
    }

    private function brandRules(): array
    {
        return [
            'brand' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:300'],
        ];
    }

    private function storeBrandFile(Request $request): ?array
    {
        if (! $request->hasFile('brand')) {
            return null;
        }

        $file = $request->file('brand');
        $originalName = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();

        $fileName = uniqid('brand_', true).'.'.$extension;

        $destination = public_path('files/brands');

        if (! is_dir($destination)) {
            mkdir($destination, 0755, true);
        }

        $file->move($destination, $fileName);

        return [
            'brand_original_name' => $originalName,
            'brand_file_name' => $fileName,
        ];
    }

    private function deleteBrandFile(?ShootingRange $shootingRange): void
    {
        if (! $shootingRange?->brand_file_name) {
            return;
        }

        $path = public_path('files/brands/'.$shootingRange->brand_file_name);

        if (file_exists($path)) {
            unlink($path);
        }
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\ShootingRange;
use App\Services\ShootingRangeAvailabilityService;
use App\Support\StructuredData;
use App\Support\Voivodeships;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Publiczny katalog strzelnic: cała Polska, województwa i miejscowości.
 * Każdy poziom ma własny, indeksowalny adres URL (SEO / GEO).
 */
class ShootingRangeDirectoryController extends Controller
{
    /**
     * Miejscowość potrzebuje co najmniej tylu strzelnic, żeby jej strona
     * była indeksowana i trafiła do sitemapy (unikamy "cienkich" stron).
     */
    public const MIN_TOWN_RANGES = 2;

    public function __construct(
        private readonly ShootingRangeAvailabilityService $availability
    ) {}

    /**
     * /lista-strzelnic – cała Polska + obsługa starych parametrów (?m=, ?w=).
     */
    public function index(Request $request)
    {
        $online = $request->boolean('online') ? ['online' => 1] : [];

        // Stare linki: /lista-strzelnic?m=Kraków -> /lista-strzelnic/malopolskie (301)
        if ($request->filled('m')) {
            $slug = Voivodeships::slugForCapital((string) $request->query('m'));

            return redirect()->to(
                $slug
                    ? route('shootings.voivodeship', ['voivodeship' => $slug] + $online)
                    : route('shootings.list', $online),
                301
            );
        }

        // Formularz filtra bez JavaScriptu: ?w=mazowieckie
        if ($request->filled('w')) {
            $slug = (string) $request->query('w');

            return redirect()->to(
                Voivodeships::exists($slug)
                    ? route('shootings.voivodeship', ['voivodeship' => $slug] + $online)
                    : route('shootings.list', $online)
            );
        }

        $ranges = $this->withAvailability($this->visibleRanges());
        $countsByVoivodeship = $ranges
            ->groupBy(fn (ShootingRange $range) => Voivodeships::slugFor($range->voivodeship))
            ->map->count();

        $stats = $this->stats($ranges);

        $intro = [
            sprintf(
                'W katalogu strzelnice.org znajdziesz %s w całej Polsce%s. Każdy profil zawiera adres, dane kontaktowe i listę osi strzeleckich z ich długością oraz liczbą stanowisk.',
                $this->pluralRanges($stats['count']),
                $stats['online'] > 0
                    ? sprintf(', w tym %d z rezerwacją online', $stats['online'])
                    : ''
            ),
            'Wybierz województwo, aby zobaczyć strzelnice w swojej okolicy, albo skorzystaj z mapy strzelnic.',
        ];

        $regionLinks = collect(Voivodeships::names())
            ->map(fn (string $name, string $slug) => [
                'name' => $name,
                'hint' => implode(', ', Voivodeships::capitals($slug)),
                'url' => route('shootings.voivodeship', ['voivodeship' => $slug]),
                'count' => $countsByVoivodeship[$slug] ?? 0,
            ])
            ->values();

        $breadcrumbs = [
            ['name' => 'Strzelnice.org', 'url' => url('/')],
            ['name' => 'Lista strzelnic', 'url' => route('shootings.list')],
        ];

        return $this->render($request, $ranges, [
            'heading' => 'Strzelnice w Polsce',
            'intro' => $intro,
            'regionLinks' => $regionLinks,
            'regionLinksTitle' => 'Strzelnice według województw',
            'breadcrumbs' => $breadcrumbs,
            'faq' => $this->faq($stats, 'w Polsce'),
            'selectedVoivodeship' => null,
            'seo' => [
                'title' => 'Strzelnice w Polsce – lista strzelnic i rezerwacja online | strzelnice.org',
                'description' => sprintf(
                    'Lista %d strzelnic w Polsce z adresami, osiami strzeleckimi i rezerwacją online. Znajdź strzelnicę w swoim województwie.',
                    $stats['count']
                ),
                'keywords' => 'strzelnice w Polsce, lista strzelnic, strzelnica, rezerwacja strzelnicy',
                'canonical_url' => route('shootings.list'),
            ],
        ]);
    }

    /**
     * /lista-strzelnic/{województwo}
     */
    public function voivodeship(Request $request, string $voivodeship)
    {
        abort_unless(Voivodeships::exists($voivodeship), 404);

        $name = Voivodeships::name($voivodeship);
        $locative = Voivodeships::locative($voivodeship);
        $capitals = Voivodeships::capitals($voivodeship);

        $ranges = $this->withAvailability($this->visibleRanges($name));
        $stats = $this->stats($ranges);

        $towns = $this->townLinks($ranges, $voivodeship);

        $intro = $stats['count'] > 0
            ? [
                sprintf(
                    'W województwie %s znajdziesz w naszym katalogu %s%s.%s',
                    $locative,
                    $this->pluralRanges($stats['count']),
                    $stats['online'] > 0
                        ? sprintf(', w tym %d z rezerwacją online', $stats['online'])
                        : '',
                    $towns->count() > 1
                        ? ' Najwięcej obiektów jest w miejscowościach: '.$towns->take(5)->pluck('name')->implode(', ').'.'
                        : ''
                ),
                $this->lanesSentence($stats),
            ]
            : [
                sprintf(
                    'Nie mamy jeszcze strzelnic z województwa %s. Zarządzasz strzelnicą w tym regionie? Dodaj ją do katalogu bezpłatnie.',
                    $name
                ),
            ];

        $breadcrumbs = [
            ['name' => 'Strzelnice.org', 'url' => url('/')],
            ['name' => 'Lista strzelnic', 'url' => route('shootings.list')],
            ['name' => 'Województwo '.$name, 'url' => route('shootings.voivodeship', ['voivodeship' => $voivodeship])],
        ];

        $capitalsText = implode(', ', $capitals);

        return $this->render($request, $ranges, [
            'heading' => 'Strzelnice w województwie '.$locative,
            'intro' => array_values(array_filter($intro)),
            'regionLinks' => $towns->filter(fn (array $town) => $town['indexable'])->values(),
            'regionLinksTitle' => 'Strzelnice według miejscowości',
            'breadcrumbs' => $breadcrumbs,
            'faq' => $this->faq($stats, 'w województwie '.$locative),
            'selectedVoivodeship' => $voivodeship,
            'seo' => [
                'title' => sprintf('Strzelnice %s i województwo %s – lista i rezerwacja online', $capitalsText, $name),
                'description' => sprintf(
                    'Strzelnice w województwie %s (%s i okolice) – adresy, osie strzeleckie, kontakt i rezerwacja stanowiska online. W katalogu: %d.',
                    $locative,
                    $capitalsText,
                    $stats['count']
                ),
                'keywords' => collect($capitals)
                    ->map(fn ($city) => 'strzelnica '.$city)
                    ->push('strzelnice '.$name, 'strzelnica województwo '.$name)
                    ->implode(', '),
                'canonical_url' => route('shootings.voivodeship', ['voivodeship' => $voivodeship]),
                'robots' => $stats['count'] > 0 ? 'index,follow' : 'noindex,follow',
            ],
        ]);
    }

    /**
     * /lista-strzelnic/{województwo}/{miejscowość}
     */
    public function town(Request $request, string $voivodeship, string $town)
    {
        abort_unless(Voivodeships::exists($voivodeship), 404);

        $name = Voivodeships::name($voivodeship);
        $locative = Voivodeships::locative($voivodeship);

        $voivodeshipRanges = $this->visibleRanges($name);
        $ranges = $voivodeshipRanges
            ->filter(fn (ShootingRange $range) => Voivodeships::townSlug($range->town) === $town)
            ->values();

        abort_if($ranges->isEmpty(), 404);

        $this->withAvailability($ranges);

        $townName = $ranges->first()->town;
        $stats = $this->stats($ranges);
        $indexable = $stats['count'] >= self::MIN_TOWN_RANGES;

        $otherTowns = $this->townLinks($voivodeshipRanges, $voivodeship)
            ->filter(fn (array $item) => $item['indexable'] && $item['slug'] !== $town)
            ->values();

        $breadcrumbs = [
            ['name' => 'Strzelnice.org', 'url' => url('/')],
            ['name' => 'Lista strzelnic', 'url' => route('shootings.list')],
            ['name' => 'Województwo '.$name, 'url' => route('shootings.voivodeship', ['voivodeship' => $voivodeship])],
            ['name' => $townName, 'url' => route('shootings.town', ['voivodeship' => $voivodeship, 'town' => $town])],
        ];

        return $this->render($request, $ranges, [
            'heading' => 'Strzelnice '.$townName,
            'intro' => array_values(array_filter([
                sprintf(
                    'W miejscowości %s (województwo %s) znajdziesz w naszym katalogu %s%s.',
                    $townName,
                    $name,
                    $this->pluralRanges($stats['count']),
                    $stats['online'] > 0 ? sprintf(', w tym %d z rezerwacją online', $stats['online']) : ''
                ),
                $this->lanesSentence($stats),
                sprintf(
                    'Szukasz szerzej? Zobacz wszystkie strzelnice w województwie %s.',
                    $locative
                ),
            ])),
            'regionLinks' => $otherTowns,
            'regionLinksTitle' => 'Inne miejscowości w województwie '.$locative,
            'breadcrumbs' => $breadcrumbs,
            'faq' => $this->faq($stats, 'w miejscowości '.$townName),
            'selectedVoivodeship' => $voivodeship,
            'seo' => [
                'title' => sprintf('Strzelnica %s – lista strzelnic i rezerwacja online | strzelnice.org', $townName),
                'description' => sprintf(
                    'Strzelnice w miejscowości %s (woj. %s): adresy, osie strzeleckie, kontakt i rezerwacja stanowiska online.',
                    $townName,
                    $name
                ),
                'keywords' => sprintf('strzelnica %1$s, strzelnice %1$s, strzelnica %2$s', $townName, $name),
                'canonical_url' => route('shootings.town', ['voivodeship' => $voivodeship, 'town' => $town]),
                'robots' => $indexable ? 'index,follow' : 'noindex,follow',
            ],
        ]);
    }

    /**
     * Wspólne renderowanie listy.
     */
    private function render(Request $request, Collection $ranges, array $data)
    {
        $onlineReservationOnly = $request->boolean('online');

        $shootings = $ranges
            ->when(
                $onlineReservationOnly,
                fn ($shootings) => $shootings->filter(
                    fn (ShootingRange $shootingRange) => $shootingRange->can_reserve_online
                )
            )
            ->values();

        // Strona z filtrem "tylko online" nie powinna konkurować z wersją bazową.
        if ($onlineReservationOnly) {
            $data['seo']['robots'] = 'noindex,follow';
        }

        $data['seo']['schema'] = array_values(array_filter([
            StructuredData::breadcrumbs($data['breadcrumbs']),
            StructuredData::itemList($ranges, $data['heading']),
            StructuredData::faq($data['faq']),
        ]));

        return view('shooting_ranges.index', $data + [
            'shootings' => $shootings,
            'voivodeships' => Voivodeships::names(),
            'onlineReservationOnly' => $onlineReservationOnly,
        ]);
    }

    private function visibleRanges(?string $voivodeshipName = null): Collection
    {
        return ShootingRange::query()
            ->where('is_visible', true)
            ->where('is_archived', false)
            ->when(
                $voivodeshipName,
                fn ($query) => $query->where('voivodeship', $voivodeshipName)
            )
            ->with([
                'lanes',
                'openingHours',
                'intervals',
                'employees',
            ])
            ->orderBy('name')
            ->get();
    }

    /**
     * Dodaje do każdej strzelnicy flagę can_reserve_online.
     */
    private function withAvailability(Collection $ranges): Collection
    {
        $today = Carbon::today();

        return $ranges->each(function (ShootingRange $shootingRange) use ($today) {
            $shootingRange->can_reserve_online = $this->availability
                ->basicRequirements($shootingRange, $today)['allowed'];
        });
    }

    /**
     * Miejscowości z liczbą strzelnic, posortowane malejąco.
     */
    private function townLinks(Collection $ranges, string $voivodeship): Collection
    {
        return $ranges
            ->filter(fn (ShootingRange $range) => filled($range->town))
            ->groupBy(fn (ShootingRange $range) => Voivodeships::townSlug($range->town))
            ->map(fn (Collection $group, string $slug) => [
                'slug' => $slug,
                'name' => $group->first()->town,
                'hint' => null,
                'count' => $group->count(),
                'indexable' => $group->count() >= self::MIN_TOWN_RANGES,
                'url' => route('shootings.town', ['voivodeship' => $voivodeship, 'town' => $slug]),
            ])
            ->sortBy([['count', 'desc'], ['name', 'asc']])
            ->values();
    }

    /**
     * Statystyki na podstawie realnych danych – używane w treści i FAQ.
     */
    private function stats(Collection $ranges): array
    {
        $lanes = $ranges->flatMap->lanes;
        $online = $ranges->filter(fn (ShootingRange $range) => $range->can_reserve_online);

        $longest = $ranges
            ->map(fn (ShootingRange $range) => [
                'range' => $range,
                'length' => (int) $range->lanes->max('length'),
            ])
            ->sortByDesc('length')
            ->first();

        return [
            'count' => $ranges->count(),
            'online' => $online->count(),
            'onlineNames' => $online->pluck('name')->take(5)->all(),
            'maxLane' => (int) $lanes->max('length'),
            'longestRange' => $longest && $longest['length'] > 0 ? $longest['range'] : null,
            'with100m' => $ranges->filter(fn (ShootingRange $range) => $range->lanes->max('length') >= 100)->count(),
            'dynamic' => $ranges->filter(fn (ShootingRange $range) => $range->lanes->contains('allows_dynamic_shooting', true))->count(),
        ];
    }

    private function lanesSentence(array $stats): ?string
    {
        if ($stats['count'] === 0 || $stats['maxLane'] === 0) {
            return null;
        }

        $parts = [sprintf('Najdłuższa oś strzelecka ma %d m.', $stats['maxLane'])];

        if ($stats['with100m'] > 0) {
            $parts[] = sprintf('Obiekty z osią co najmniej 100 m: %d.', $stats['with100m']);
        }

        if ($stats['dynamic'] > 0) {
            $parts[] = sprintf('Obiekty umożliwiające strzelanie dynamiczne: %d.', $stats['dynamic']);
        }

        return implode(' ', $parts);
    }

    /**
     * Pytania i odpowiedzi oparte na danych z katalogu.
     *
     * @return array<int, array{question: string, answer: string}>
     */
    private function faq(array $stats, string $where): array
    {
        if ($stats['count'] === 0) {
            return [];
        }

        $faq = [
            [
                'question' => sprintf('Ile jest strzelnic %s?', $where),
                'answer' => sprintf(
                    'W katalogu strzelnice.org znajdziesz obecnie %s %s. Lista jest na bieżąco uzupełniana przez zarządców strzelnic.',
                    $this->pluralRanges($stats['count']),
                    $where
                ),
            ],
        ];

        if ($stats['online'] > 0) {
            $faq[] = [
                'question' => sprintf('Gdzie można zarezerwować strzelnicę online %s?', $where),
                'answer' => sprintf(
                    'Rezerwacja stanowiska przez strzelnice.org jest dostępna m.in. w: %s (łącznie obiektów z rezerwacją online: %d). Wybierasz oś, dzień i godzinę, a stanowisko czeka na Ciebie.',
                    implode(', ', $stats['onlineNames']),
                    $stats['online']
                ),
            ];
        }

        if ($stats['longestRange']) {
            $faq[] = [
                'question' => sprintf('Gdzie jest najdłuższa oś strzelecka %s?', $where),
                'answer' => sprintf(
                    'Najdłuższą oś w naszym katalogu (%d m) ma %s (%s).',
                    $stats['maxLane'],
                    $stats['longestRange']->name,
                    $stats['longestRange']->town
                ),
            ];
        }

        $faq[] = [
            'question' => 'Czy do strzelania na strzelnicy potrzebne jest pozwolenie na broń?',
            'answer' => 'Nie zawsze. Osoba bez pozwolenia może zwykle strzelać z broni udostępnionej przez strzelnicę pod nadzorem prowadzącego strzelanie. Szczegółowe zasady, w tym wymagany wiek i dokumenty, określa regulamin danej strzelnicy – warto sprawdzić go przed wizytą.',
        ];

        return $faq;
    }

    /**
     * Liczba strzelnic w bierniku: "1 strzelnicę", "3 strzelnice", "12 strzelnic".
     */
    private function pluralRanges(int $count): string
    {
        $mod10 = $count % 10;
        $mod100 = $count % 100;

        $noun = match (true) {
            $count === 1 => 'strzelnicę',
            $mod10 >= 2 && $mod10 <= 4 && ($mod100 < 12 || $mod100 > 14) => 'strzelnice',
            default => 'strzelnic',
        };

        return $count.' '.$noun;
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\ShootingRange;
use App\Support\Voivodeships;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * /sitemap.xml dla wyszukiwarek oraz /llms.txt dla modeli językowych (GEO).
 */
class SitemapController extends Controller
{
    private const CACHE_SECONDS = 3600;

    public function index()
    {
        $xml = Cache::remember('seo:sitemap.xml', self::CACHE_SECONDS, function () {
            $entries = $this->entries();

            $lines = [
                '<?xml version="1.0" encoding="UTF-8"?>',
                '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">',
            ];

            foreach ($entries as $entry) {
                $lines[] = '  <url>';
                $lines[] = '    <loc>'.e($entry['loc']).'</loc>';

                if (! empty($entry['lastmod'])) {
                    $lines[] = '    <lastmod>'.$entry['lastmod'].'</lastmod>';
                }

                $lines[] = '  </url>';
            }

            $lines[] = '</urlset>';

            return implode("\n", $lines)."\n";
        });

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function llms()
    {
        $text = Cache::remember('seo:llms.txt', self::CACHE_SECONDS, function () {
            $ranges = $this->indexableRanges();
            $byVoivodeship = $ranges->groupBy(fn (ShootingRange $range) => Voivodeships::slugFor($range->voivodeship) ?? 'inne');

            $out = [];
            $out[] = '# Strzelnice.org';
            $out[] = '';
            $out[] = sprintf(
                '> Strzelnice.org to polska wyszukiwarka strzelnic z rezerwacją stanowisk online. Katalog obejmuje %d strzelnic w całej Polsce: adresy, dane kontaktowe, osie strzeleckie (długość, liczba stanowisk, strzelanie dynamiczne) oraz dostępność rezerwacji.',
                $ranges->count()
            );
            $out[] = '';
            $out[] = 'Jak to działa: użytkownik wybiera lokalizację i datę, sprawdza wolne terminy, loguje się i rezerwuje stanowisko na wybranej osi. Zarządcy strzelnic otrzymują panel do obsługi rezerwacji, kalendarza, płatności online i pracowników.';
            $out[] = '';
            $out[] = '## Najważniejsze strony';
            $out[] = '';
            $out[] = '- [Wyszukiwarka strzelnic]('.url('/').'): wyszukiwanie wolnych stanowisk po lokalizacji, dacie i długości osi';
            $out[] = '- [Lista strzelnic w Polsce]('.route('shootings.list').'): wszystkie strzelnice z podziałem na województwa';
            $out[] = '- [Mapa strzelnic]('.route('maps').'): strzelnice na mapie';
            $out[] = '- [Oferta dla strzelnic]('.route('offers').'): system rezerwacji online dla zarządców strzelnic';
            $out[] = '- [Kontakt]('.route('contact').')';
            $out[] = '';
            $out[] = '## Strzelnice według województw';
            $out[] = '';

            foreach (Voivodeships::names() as $slug => $name) {
                $count = $byVoivodeship->get($slug)?->count() ?? 0;

                if ($count > 0) {
                    $out[] = sprintf(
                        '- [Strzelnice – województwo %s](%s): %d, główne miasta: %s',
                        $name,
                        route('shootings.voivodeship', ['voivodeship' => $slug]),
                        $count,
                        implode(', ', Voivodeships::capitals($slug))
                    );
                }
            }

            foreach (Voivodeships::names() as $slug => $name) {
                $group = $byVoivodeship->get($slug);

                if (! $group || $group->isEmpty()) {
                    continue;
                }

                $out[] = '';
                $out[] = '## Strzelnice – województwo '.$name;
                $out[] = '';

                foreach ($group as $range) {
                    $lanes = $range->lanes
                        ->pluck('length')
                        ->filter()
                        ->map(fn ($length) => (int) $length.' m')
                        ->implode(', ');

                    $out[] = sprintf(
                        '- [%s](%s): %s%s',
                        $range->name,
                        $range->public_link,
                        $range->full_address ?: $range->town,
                        $lanes !== '' ? '; osie: '.$lanes : ''
                    );
                }
            }

            return implode("\n", $out)."\n";
        });

        return response($text, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    /**
     * @return array<int, array{loc: string, lastmod: ?string}>
     */
    private function entries(): array
    {
        $ranges = $this->indexableRanges();
        $latest = optional($ranges->max('updated_at'))->toAtomString();

        $entries = [
            ['loc' => url('/'), 'lastmod' => $latest],
            ['loc' => route('shootings.list'), 'lastmod' => $latest],
            ['loc' => route('maps'), 'lastmod' => $latest],
            ['loc' => route('offers'), 'lastmod' => null],
            ['loc' => route('shooting-range-zone'), 'lastmod' => null],
            ['loc' => route('contact'), 'lastmod' => null],
        ];

        // Województwa i miejscowości
        $byVoivodeship = $ranges->groupBy(fn (ShootingRange $range) => Voivodeships::slugFor($range->voivodeship));

        foreach (Voivodeships::names() as $slug => $name) {
            /** @var Collection|null $group */
            $group = $byVoivodeship->get($slug);

            if (! $group || $group->isEmpty()) {
                continue;
            }

            $entries[] = [
                'loc' => route('shootings.voivodeship', ['voivodeship' => $slug]),
                'lastmod' => optional($group->max('updated_at'))->toAtomString(),
            ];

            $group
                ->filter(fn (ShootingRange $range) => filled($range->town))
                ->groupBy(fn (ShootingRange $range) => Voivodeships::townSlug($range->town))
                ->filter(fn (Collection $town) => $town->count() >= ShootingRangeDirectoryController::MIN_TOWN_RANGES)
                ->each(function (Collection $town, string $townSlug) use (&$entries, $slug) {
                    $entries[] = [
                        'loc' => route('shootings.town', ['voivodeship' => $slug, 'town' => $townSlug]),
                        'lastmod' => optional($town->max('updated_at'))->toAtomString(),
                    ];
                });
        }

        // Profile strzelnic
        foreach ($ranges as $range) {
            $entries[] = [
                'loc' => $range->public_link,
                'lastmod' => optional($range->updated_at)->toAtomString(),
            ];
        }

        // Strony CMS (regulaminy, polityka prywatności itp.)
        Page::query()
            ->where('is_active', true)
            ->get()
            ->reject(fn (Page $page) => Str::contains((string) $page->robots, 'noindex'))
            ->reject(fn (Page $page) => filled($page->canonical_url) && $page->canonical_url !== url('/'.$page->navigation_name))
            ->each(function (Page $page) use (&$entries) {
                $entries[] = [
                    'loc' => url('/'.$page->navigation_name),
                    'lastmod' => optional($page->updated_at)->toAtomString(),
                ];
            });

        return collect($entries)->unique('loc')->values()->all();
    }

    private function indexableRanges(): Collection
    {
        return ShootingRange::query()
            ->where('is_visible', true)
            ->where('is_archived', false)
            ->whereNotNull('endpoint')
            ->where('endpoint', '!=', '')
            ->with('lanes')
            ->orderBy('name')
            ->get()
            ->reject(fn (ShootingRange $range) => Str::contains((string) $range->robots, 'noindex'));
    }
}

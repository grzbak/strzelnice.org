<?php

namespace App\Support;

use App\Models\ShootingRange;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Dane strukturalne schema.org (JSON-LD) dla wyszukiwarek i modeli AI.
 * Każda metoda zwraca tablicę gotową do json_encode albo null.
 */
class StructuredData
{
    private const DAYS = [
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
        7 => 'Sunday',
    ];

    public static function website(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            '@id' => url('/').'#website',
            'name' => 'Strzelnice.org',
            'alternateName' => 'strzelnice.org – wyszukiwarka i rezerwacja strzelnic',
            'url' => url('/'),
            'inLanguage' => 'pl-PL',
            'description' => 'Wyszukiwarka strzelnic w Polsce z rezerwacją stanowisk online.',
            'publisher' => ['@id' => url('/').'#organization'],
        ];
    }

    public static function organization(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            '@id' => url('/').'#organization',
            'name' => 'Strzelnice.org',
            'url' => url('/'),
            'logo' => asset('img/strzelnice_brand.png'),
            'contactPoint' => [
                '@type' => 'ContactPoint',
                'contactType' => 'customer support',
                'url' => url('/kontakt'),
                'availableLanguage' => ['pl'],
            ],
        ];
    }

    /**
     * @param  array<int, array{name: string, url: string}>  $items
     */
    public static function breadcrumbs(array $items): ?array
    {
        if (count($items) < 2) {
            return null;
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => collect($items)
                ->values()
                ->map(fn (array $item, int $index) => [
                    '@type' => 'ListItem',
                    'position' => $index + 1,
                    'name' => $item['name'],
                    'item' => $item['url'],
                ])
                ->all(),
        ];
    }

    public static function itemList(Collection $ranges, string $name): ?array
    {
        if ($ranges->isEmpty()) {
            return null;
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            'name' => $name,
            'numberOfItems' => $ranges->count(),
            'itemListElement' => $ranges
                ->values()
                ->map(fn (ShootingRange $range, int $index) => [
                    '@type' => 'ListItem',
                    'position' => $index + 1,
                    'url' => $range->public_link,
                    'name' => $range->name,
                ])
                ->all(),
        ];
    }

    /**
     * @param  array<int, array{question: string, answer: string}>  $faq
     */
    public static function faq(array $faq): ?array
    {
        if ($faq === []) {
            return null;
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => collect($faq)
                ->map(fn (array $item) => [
                    '@type' => 'Question',
                    'name' => $item['question'],
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => $item['answer'],
                    ],
                ])
                ->all(),
        ];
    }

    /**
     * Profil strzelnicy jako SportsActivityLocation (podtyp LocalBusiness).
     */
    public static function shootingRange(ShootingRange $range, bool $canReserve = false): array
    {
        $description = trim(Str::limit(
            preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) $range->description))),
            500
        ));

        $street = filled($range->street)
            ? trim($range->street.($range->houseNumber ? ' '.$range->houseNumber : ''))
            : '';

        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'SportsActivityLocation',
            '@id' => $range->public_link.'#strzelnica',
            'name' => $range->name,
            'url' => $range->public_link,
            'description' => $description ?: null,
            'image' => $range->brand_url,
            'logo' => $range->brand_url,
            'telephone' => $range->telephone1 ?: null,
            'email' => $range->email ?: null,
            'address' => array_filter([
                '@type' => 'PostalAddress',
                'streetAddress' => $street ?: null,
                'postalCode' => $range->zipCode ?: null,
                'addressLocality' => $range->town ?: null,
                'addressRegion' => $range->voivodeship ? 'województwo '.$range->voivodeship : null,
                'addressCountry' => 'PL',
            ]),
            'hasMap' => $range->navLink ?: null,
            'sameAs' => array_values(array_filter([
                $range->url,
                $range->facebook,
                $range->instagram,
            ], fn ($value) => filled($value) && Str::startsWith($value, ['http://', 'https://']))),
        ];

        if ($range->lat && $range->lng) {
            $data['geo'] = [
                '@type' => 'GeoCoordinates',
                'latitude' => $range->lat,
                'longitude' => $range->lng,
            ];
        }

        $openingHours = self::openingHours($range);

        if ($openingHours !== []) {
            $data['openingHoursSpecification'] = $openingHours;
        }

        $lanes = $range->lanes->map(fn ($lane) => [
            '@type' => 'LocationFeatureSpecification',
            'name' => trim(sprintf(
                '%s – %d m, stanowiska: %d%s',
                $lane->name,
                (int) $lane->length,
                (int) $lane->numberOfPositions,
                $lane->allows_dynamic_shooting ? ', strzelanie dynamiczne' : ''
            )),
            'value' => true,
        ])->values()->all();

        if ($lanes !== []) {
            $data['amenityFeature'] = $lanes;
        }

        if ($canReserve) {
            $data['potentialAction'] = [
                '@type' => 'ReserveAction',
                'target' => [
                    '@type' => 'EntryPoint',
                    'urlTemplate' => url('/rezerwacje/'.$range->id),
                    'inLanguage' => 'pl-PL',
                    'actionPlatform' => [
                        'https://schema.org/DesktopWebPlatform',
                        'https://schema.org/MobileWebPlatform',
                    ],
                ],
                'result' => [
                    '@type' => 'Reservation',
                    'name' => 'Rezerwacja stanowiska strzeleckiego',
                ],
            ];
        }

        return self::clean($data);
    }

    /**
     * Godziny otwarcia – pomijamy domyślne wartości 00:00–23:59 tworzone
     * automatycznie dla nowych strzelnic, bo nie są prawdziwymi godzinami.
     */
    public static function openingHours(ShootingRange $range): array
    {
        if (! $range->hasRealOpeningHours()) {
            return [];
        }

        return $range->openingHours
            ->sortBy('day_of_week')
            ->filter(fn ($hour) => isset(self::DAYS[$hour->day_of_week]))
            ->map(fn ($hour) => [
                '@type' => 'OpeningHoursSpecification',
                'dayOfWeek' => 'https://schema.org/'.self::DAYS[$hour->day_of_week],
                'opens' => $hour->opens_at?->format('H:i'),
                'closes' => $hour->closes_at?->format('H:i'),
            ])
            ->values()
            ->all();
    }

    /**
     * Usuwa puste wartości (null, '', []) na pierwszym poziomie.
     */
    private static function clean(array $data): array
    {
        return array_filter($data, fn ($value) => ! ($value === null || $value === '' || $value === []));
    }
}

<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Słownik województw używany przez publiczne strony list strzelnic,
 * sitemapę i dane strukturalne.
 */
class Voivodeships
{
    /**
     * slug => [nazwa w bazie, forma "w województwie ...", główne miasta]
     */
    private const ALL = [
        'dolnoslaskie' => ['dolnośląskie', 'dolnośląskim', ['Wrocław']],
        'kujawsko-pomorskie' => ['kujawsko-pomorskie', 'kujawsko-pomorskim', ['Bydgoszcz', 'Toruń']],
        'lubelskie' => ['lubelskie', 'lubelskim', ['Lublin']],
        'lubuskie' => ['lubuskie', 'lubuskim', ['Gorzów Wielkopolski', 'Zielona Góra']],
        'lodzkie' => ['łódzkie', 'łódzkim', ['Łódź']],
        'malopolskie' => ['małopolskie', 'małopolskim', ['Kraków']],
        'mazowieckie' => ['mazowieckie', 'mazowieckim', ['Warszawa']],
        'opolskie' => ['opolskie', 'opolskim', ['Opole']],
        'podkarpackie' => ['podkarpackie', 'podkarpackim', ['Rzeszów']],
        'podlaskie' => ['podlaskie', 'podlaskim', ['Białystok']],
        'pomorskie' => ['pomorskie', 'pomorskim', ['Gdańsk']],
        'slaskie' => ['śląskie', 'śląskim', ['Katowice']],
        'swietokrzyskie' => ['świętokrzyskie', 'świętokrzyskim', ['Kielce']],
        'warminsko-mazurskie' => ['warmińsko-mazurskie', 'warmińsko-mazurskim', ['Olsztyn']],
        'wielkopolskie' => ['wielkopolskie', 'wielkopolskim', ['Poznań']],
        'zachodniopomorskie' => ['zachodniopomorskie', 'zachodniopomorskim', ['Szczecin']],
    ];

    /**
     * @return array<string, string> slug => nazwa
     */
    public static function names(): array
    {
        return array_map(fn (array $item) => $item[0], self::ALL);
    }

    public static function exists(string $slug): bool
    {
        return isset(self::ALL[$slug]);
    }

    public static function name(string $slug): ?string
    {
        return self::ALL[$slug][0] ?? null;
    }

    /**
     * "mazowieckim" – do zdań typu "Strzelnice w województwie mazowieckim".
     */
    public static function locative(string $slug): ?string
    {
        return self::ALL[$slug][1] ?? null;
    }

    /**
     * @return array<int, string>
     */
    public static function capitals(string $slug): array
    {
        return self::ALL[$slug][2] ?? [];
    }

    /**
     * Slug na podstawie wartości zapisanej w bazie (np. "Śląskie", "slaskie").
     */
    public static function slugFor(?string $value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        $slug = Str::slug(mb_strtolower(trim($value)));

        return self::exists($slug) ? $slug : null;
    }

    /**
     * Slug województwa dla miasta wojewódzkiego (stare linki ?m=Kraków).
     */
    public static function slugForCapital(string $city): ?string
    {
        $city = mb_strtolower(trim($city));

        foreach (self::ALL as $slug => $item) {
            foreach ($item[2] as $capital) {
                if (mb_strtolower($capital) === $city) {
                    return $slug;
                }
            }
        }

        return null;
    }

    public static function townSlug(?string $town): string
    {
        return Str::slug((string) $town);
    }
}

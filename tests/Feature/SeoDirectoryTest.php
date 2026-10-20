<?php

namespace Tests\Feature;

use App\Models\ShootingRange;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SeoDirectoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Trigger z domyślnymi godzinami używa NOW() (MySQL) – w SQLite go pomijamy.
        DB::unprepared('DROP TRIGGER IF EXISTS shooting_ranges_after_insert_default_opening_hours');
        Cache::flush();

        foreach ([
            ['Strzelnica Alfa', 'Warszawa', 'strzelnicaalfa'],
            ['Strzelnica Beta', 'Warszawa', 'strzelnicabeta'],
            ['Strzelnica Gamma', 'Węgrów', 'strzelnicagamma'],
        ] as [$name, $town, $endpoint]) {
            $range = ShootingRange::create([
                'name' => $name,
                'voivodeship' => 'mazowieckie',
                'town' => $town,
                'zipCode' => '00-001',
                'navLink' => 'https://maps.google.com',
                'telephone1' => '500100200',
                'email' => 'kontakt@example.com',
                'type' => 'otwarta',
                'is_visible' => true,
                'is_public_access' => true,
                'endpoint' => $endpoint,
                'lat' => 52.2,
                'lng' => 21.0,
            ]);

            DB::table('lanes')->insert([
                'shooting_range_id' => $range->id,
                'name' => 'Setka',
                'length' => 100,
                'numberOfPositions' => 5,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function test_voivodeship_page_has_own_title_h1_and_canonical(): void
    {
        $this->get('/lista-strzelnic/mazowieckie')
            ->assertOk()
            ->assertSee('<h1 class="mb-3 h2">Strzelnice w województwie mazowieckim</h1>', false)
            ->assertSee('<link rel="canonical" href="'.url('/lista-strzelnic/mazowieckie').'">', false)
            ->assertSee('"@type":"FAQPage"', false)
            ->assertSee('Strzelnica Alfa');
    }

    public function test_town_pages_are_indexed_only_with_enough_ranges(): void
    {
        $this->get('/lista-strzelnic/mazowieckie/warszawa')
            ->assertOk()
            ->assertSee('<meta name="robots" content="index,follow">', false);

        $this->get('/lista-strzelnic/mazowieckie/wegrow')
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex,follow">', false);

        $this->get('/lista-strzelnic/mazowieckie/nieistniejace')->assertNotFound();
        $this->get('/lista-strzelnic/nieistniejace')->assertNotFound();
    }

    public function test_old_city_filter_redirects_permanently(): void
    {
        $this->get('/lista-strzelnic?m=Warszawa')
            ->assertStatus(301)
            ->assertRedirect('/lista-strzelnic/mazowieckie');
    }

    public function test_sitemap_lists_ranges_and_regions(): void
    {
        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee(url('/strzelnicaalfa'), false)
            ->assertSee(url('/lista-strzelnic/mazowieckie'), false)
            ->assertSee(url('/lista-strzelnic/mazowieckie/warszawa'), false)
            ->assertDontSee(url('/lista-strzelnic/mazowieckie/wegrow'), false);
    }

    public function test_profile_has_structured_data_and_skips_placeholder_hours(): void
    {
        DB::table('shooting_range_opening_hours')->insert(collect(range(1, 7))->map(fn ($day) => [
            'shooting_range_id' => ShootingRange::where('endpoint', 'strzelnicaalfa')->value('id'),
            'day_of_week' => $day,
            'opens_at' => '00:00:00',
            'closes_at' => '23:59:59',
            'created_at' => now(),
            'updated_at' => now(),
        ])->all());

        $this->get('/strzelnicaalfa')
            ->assertOk()
            ->assertSee('"@type":"SportsActivityLocation"', false)
            ->assertSee('"@type":"BreadcrumbList"', false)
            ->assertDontSee('openingHoursSpecification', false)
            ->assertDontSee('Godziny otwarcia');
    }

    public function test_llms_txt_is_available(): void
    {
        $this->get('/llms.txt')
            ->assertOk()
            ->assertSee('# Strzelnice.org')
            ->assertSee('Strzelnica Beta');
    }
}

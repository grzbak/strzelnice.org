<?php

namespace Tests\Feature;

use App\Mail\ShootingRangeZoneAdminMail;
use App\Mail\ShootingRangeZoneConfirmationMail;
use App\Models\SystemConfiguration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ShootingRangeZoneTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('system_configurations', function (Blueprint $table) {
            $table->id();

            foreach (SystemConfiguration::DATA_FIELDS as $field) {
                if (in_array($field, [
                    'default_max_advance_booking_days',
                    'reservation_operational_months',
                    'reservation_history_months',
                ], true)) {
                    continue;
                }

                $table->string($field)->nullable();
            }

            $table->unsignedSmallInteger('default_max_advance_booking_days')
                ->default(30);
            $table->unsignedSmallInteger('reservation_operational_months')
                ->default(6);
            $table->unsignedSmallInteger('reservation_history_months')
                ->default(60);
            $table->boolean('is_archived')->default(false);
            $table->timestamps();
        });

        SystemConfiguration::create([
            'email' => 'admin@example.test',
            'is_archived' => false,
        ]);
    }

    public function test_shooting_range_zone_page_can_be_rendered(): void
    {
        $this->get(route('shooting-range-zone'))
            ->assertOk()
            ->assertSee('Strefa strzelnic')
            ->assertSee('Zarejestruj strzelnicę');
    }

    public function test_shooting_range_zone_form_sends_admin_mail_and_confirmation(): void
    {
        Mail::fake();

        $this->post(route('shooting-range-zone.send'), [
            'source' => 'hero',
            'name' => 'Jan Kowalski',
            'email' => 'jan@example.test',
            'phone' => '500 600 700',
            'shooting_range_name' => 'Strzelnica Testowa',
            'city' => 'Lublin',
            'message' => 'Proszę o kontakt.',
        ])->assertSessionHas('success');

        Mail::assertSent(
            ShootingRangeZoneAdminMail::class,
            fn (ShootingRangeZoneAdminMail $mail) => $mail->hasTo(
                'admin@example.test'
            )
        );

        Mail::assertSent(
            ShootingRangeZoneConfirmationMail::class,
            fn (ShootingRangeZoneConfirmationMail $mail) => $mail->hasTo(
                'jan@example.test'
            )
        );
    }
}

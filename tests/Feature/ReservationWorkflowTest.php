<?php

namespace Tests\Feature;

use App\Http\Controllers\ShootingsController;
use App\Models\Reservation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ReservationWorkflowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-06-29 08:00:00');

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('telephone')->nullable();
            $table->string('avatar')->nullable();
            $table->string('role')->default('klient');
            $table->boolean('is_approved')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('system_configurations', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('default_max_advance_booking_days')
                ->default(30);
            $table->unsignedSmallInteger('reservation_operational_months')
                ->default(6);
            $table->unsignedSmallInteger('reservation_history_months')
                ->default(60);
            $table->boolean('is_archived')->default(false);
            $table->timestamps();
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sender_id');
            $table->foreignId('recipient_id');
            $table->text('content');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('shooting_ranges', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('endpoint')->nullable();
            $table->string('town')->nullable();
            $table->boolean('is_visible')->default(true);
            $table->boolean('is_public_access')->default(true);
            $table->boolean('is_archived')->default(false);
            $table->unsignedSmallInteger('max_advance_booking_days')->nullable();
            $table->timestamps();
        });

        Schema::create('shooting_range_gallery_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shooting_range_id')->unique();
            $table->string('name');
            $table->string('folder_path')->unique();
            $table->unsignedBigInteger('capacity_bytes')->default(52428800);
            $table->boolean('is_visible')->default(true);
            $table->boolean('is_deletable')->default(false);
            $table->boolean('allow_upload')->default(true);
            $table->boolean('allow_delete')->default(true);
            $table->timestamps();
        });

        Schema::create('shooting_range_gallery_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id');
            $table->string('stored_name')->unique();
            $table->string('original_name');
            $table->text('full_url');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('size_bytes');
            $table->string('format', 20);
            $table->timestamps();
        });

        Schema::create('shooting_range_employee', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id');
            $table->foreignId('shooting_ranges_id');
            $table->string('role');
            $table->boolean('is_approved')->default(true);
            $table->timestamps();
        });

        Schema::create('lanes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shooting_range_id');
            $table->string('name');
            $table->unsignedInteger('length')->default(25);
            $table->unsignedInteger('numberOfPositions')->default(1);
            $table->boolean('allows_dynamic_shooting')->default(false);
            $table->boolean('is_archived')->default(false);
            $table->timestamps();
        });

        Schema::create('shooting_range_opening_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shooting_range_id');
            $table->unsignedTinyInteger('day_of_week');
            $table->time('opens_at');
            $table->time('closes_at');
            $table->timestamps();
        });

        Schema::create('shooting_range_holidays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shooting_range_id');
            $table->date('date');
            $table->string('name')->nullable();
            $table->timestamps();
        });

        Schema::create('shooting_range_interval', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shooting_range_id');
            $table->date('start_date');
            $table->date('end_date');
            $table->timestamp('datePaid')->nullable();
            $table->date('payment_due_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('shooting_range_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shooting_range_id');
            $table->foreignId('user_id')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email');
            $table->string('token')->nullable();
            $table->string('status');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('expires_soon_notified_at')->nullable();
            $table->foreignId('invited_by')->nullable();
            $table->timestamps();
        });

        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->foreignId('mod_id');
            $table->foreignId('customer_user_id')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('customer_phone')->nullable();
            $table->foreignId('created_by_user_id')->nullable();
            $table->string('source');
            $table->foreignId('shooting_range_id');
            $table->foreignId('line_id');
            $table->dateTime('start');
            $table->dateTime('end');
            $table->unsignedInteger('positions_count')->default(1);
            $table->boolean('is_dynamic_shooting')->default(false);
            $table->string('status');
            $table->foreignId('approved_by_user_id')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('cancelled_by_user_id')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('finalized_by_user_id')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('reservation_archives', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('original_reservation_id')->unique();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('mod_id')->nullable();
            $table->unsignedBigInteger('customer_user_id')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('customer_phone')->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->string('source')->nullable();
            $table->unsignedBigInteger('shooting_range_id')->nullable();
            $table->string('shooting_range_name')->nullable();
            $table->string('shooting_range_town')->nullable();
            $table->unsignedBigInteger('line_id')->nullable();
            $table->string('lane_name')->nullable();
            $table->unsignedInteger('lane_length')->nullable();
            $table->dateTime('start');
            $table->dateTime('end');
            $table->unsignedInteger('positions_count')->default(1);
            $table->boolean('is_dynamic_shooting')->default(false);
            $table->string('status');
            $table->unsignedBigInteger('approved_by_user_id')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('cancelled_by_user_id')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->unsignedBigInteger('finalized_by_user_id')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('reservation_created_at')->nullable();
            $table->timestamp('reservation_updated_at')->nullable();
            $table->dateTime('archived_at');
        });

        DB::table('system_configurations')->insert([
            'default_max_advance_booking_days' => 30,
            'reservation_operational_months' => 6,
            'reservation_history_months' => 60,
            'is_archived' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_assigned_staff_can_create_approved_guest_reservation(): void
    {
        [$admin, $rangeId, $laneId] = $this->createBookableRange('admin', true);

        $this->actingAs($admin)
            ->post(route('admin.reservations.store'), [
                'shooting_range_id' => $rangeId,
                'line_id' => $laneId,
                'date' => today()->toDateString(),
                'start_time' => '10:00',
                'end_time' => '10:30',
                'positions_count' => 1,
                'customer_type' => 'guest',
                'customer_name' => 'Jan Gość',
                'customer_email' => 'gosc@example.test',
                'customer_phone' => '500600700',
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('reservations', [
            'customer_user_id' => null,
            'customer_name' => 'Jan Gość',
            'customer_email' => 'gosc@example.test',
            'created_by_user_id' => $admin->id,
            'source' => Reservation::SOURCE_STAFF_GUEST,
            'status' => Reservation::STATUS_APPROVED,
            'approved_by_user_id' => $admin->id,
        ]);
    }

    public function test_hidden_range_calendar_is_read_only_and_blocks_new_reservations(): void
    {
        [$admin, $rangeId, $laneId] = $this->createBookableRange('admin', true);

        DB::table('shooting_ranges')
            ->where('id', $rangeId)
            ->update(['is_visible' => false]);

        $this->actingAs($admin)
            ->get(route('admin.reservations.calendar', [
                'shooting_range_id' => $rangeId,
            ]))
            ->assertOk()
            ->assertSee('Strzelnica jest ukryta.')
            ->assertSee('id="newReservationButton"', false)
            ->assertSee('disabled', false);

        $this->actingAs($admin)
            ->post(route('admin.reservations.store'), [
                'shooting_range_id' => $rangeId,
                'line_id' => $laneId,
                'date' => today()->toDateString(),
                'start_time' => '10:00',
                'end_time' => '10:30',
                'positions_count' => 1,
                'customer_type' => 'guest',
                'customer_name' => 'Jan Gość',
                'customer_email' => 'gosc@example.test',
            ])
            ->assertSessionHas(
                'error',
                'Strzelnica jest ukryta. Dodawanie i edycja rezerwacji są zablokowane.'
            );

        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_client_cannot_use_staff_guest_endpoint(): void
    {
        [, $rangeId, $laneId] = $this->createBookableRange('admin', true);
        $client = User::factory()->create([
            'role' => 'klient',
            'is_approved' => true,
        ]);

        $this->actingAs($client)
            ->post(route('admin.reservations.store'), [
                'shooting_range_id' => $rangeId,
                'line_id' => $laneId,
                'date' => today()->toDateString(),
                'start_time' => '10:00',
                'end_time' => '10:30',
                'positions_count' => 1,
                'customer_type' => 'guest',
                'customer_name' => 'Gość',
                'customer_email' => 'gosc@example.test',
            ])
            ->assertForbidden();
    }

    public function test_assigned_employee_can_create_guest_reservation(): void
    {
        [$employee, $rangeId, $laneId] = $this->createBookableRange(
            'pracownik',
            true
        );

        $this->actingAs($employee)
            ->post(route('admin.reservations.store'), [
                'shooting_range_id' => $rangeId,
                'line_id' => $laneId,
                'date' => today()->toDateString(),
                'start_time' => '11:00',
                'end_time' => '11:30',
                'positions_count' => 1,
                'customer_type' => 'guest',
                'customer_name' => 'Gość pracownika',
                'customer_email' => 'pracownik.gosc@example.test',
            ])
            ->assertSessionHas('success');
    }

    public function test_private_range_requires_invitation_valid_on_booking_date(): void
    {
        [, $rangeId, $laneId] = $this->createBookableRange('admin', false);
        $client = User::factory()->create([
            'role' => 'klient',
            'is_approved' => true,
        ]);
        $payload = [
            'shooting_range_id' => $rangeId,
            'line_id' => $laneId,
            'date' => today()->addDay()->toDateString(),
            'start_time' => '10:00',
            'end_time' => '10:30',
            'positions_count' => 1,
        ];

        $this->actingAs($client)
            ->post(route('rezerwacje.store'), $payload)
            ->assertSessionHas(
                'error',
                'Ta strzelnica wymaga aktywnego i zaakceptowanego zaproszenia.'
            );

        DB::table('shooting_range_invitations')->insert([
            'shooting_range_id' => $rangeId,
            'user_id' => $client->id,
            'email' => $client->email,
            'status' => 'accepted',
            'expires_at' => today()->addDays(2)->endOfDay(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($client)
            ->post(route('rezerwacje.store'), $payload)
            ->assertSessionHas('success');

        $this->assertDatabaseHas('reservations', [
            'customer_user_id' => $client->id,
            'source' => Reservation::SOURCE_ONLINE_ACCOUNT,
            'status' => Reservation::STATUS_PENDING,
        ]);
    }

    public function test_dynamic_reservation_uses_all_lane_positions(): void
    {
        [, $rangeId, $laneId] = $this->createBookableRange('admin', true);
        $client = User::factory()->create([
            'role' => 'klient',
            'is_approved' => true,
        ]);

        $this->actingAs($client)
            ->post(route('rezerwacje.store'), [
                'shooting_range_id' => $rangeId,
                'line_id' => $laneId,
                'date' => today()->toDateString(),
                'start_time' => '12:00',
                'end_time' => '12:30',
                'positions_count' => 1,
                'is_dynamic_shooting' => true,
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('reservations', [
            'line_id' => $laneId,
            'positions_count' => 2,
            'is_dynamic_shooting' => true,
        ]);
    }

    public function test_cancelled_dynamic_reservation_cannot_be_reactivated_when_lane_is_partly_taken(): void
    {
        [$admin, $rangeId, $laneId] = $this->createBookableRange('admin', true);
        $cancelledId = $this->insertReservation(
            $admin,
            $rangeId,
            $laneId,
            '14:00',
            '14:30',
            2,
            Reservation::STATUS_CANCELLED,
            true
        );
        $this->insertReservation(
            $admin,
            $rangeId,
            $laneId,
            '14:00',
            '14:30',
            1,
            Reservation::STATUS_APPROVED
        );

        $this->actingAs($admin)
            ->patch(route('admin.reservations.status', $cancelledId), [
                'status' => Reservation::STATUS_APPROVED,
            ])
            ->assertSessionHas(
                'error',
                'W wybranym czasie nie ma wystarczającej liczby wolnych stanowisk.'
            );

        $this->assertDatabaseHas('reservations', [
            'id' => $cancelledId,
            'status' => Reservation::STATUS_CANCELLED,
        ]);
    }

    public function test_availability_endpoint_returns_reservations_for_requested_distant_date(): void
    {
        [$admin, $rangeId, $laneId] = $this->createBookableRange('admin', true);
        $date = today()->addDays(20);
        $this->insertReservation(
            $admin,
            $rangeId,
            $laneId,
            '10:00',
            '10:30',
            1,
            Reservation::STATUS_APPROVED,
            false,
            $date
        );

        $this->getJson(route('rezerwacje.availability', [
            'id' => $rangeId,
            'date' => $date->toDateString(),
        ]))
            ->assertOk()
            ->assertJsonCount(1, 'reservations')
            ->assertJsonPath('reservations.0.line_id', $laneId);
    }

    public function test_current_interval_allows_booking_beyond_its_end_and_payment_due_dates(): void
    {
        [$admin, $rangeId, $laneId] = $this->createBookableRange('admin', true);
        $bookingDate = today()->addDays(5);

        DB::table('shooting_range_interval')
            ->where('shooting_range_id', $rangeId)
            ->update([
                'end_date' => today()->addDay(),
                'datePaid' => null,
                'payment_due_date' => today()->addDay(),
            ]);
        DB::table('shooting_range_opening_hours')->insert([
            'shooting_range_id' => $rangeId,
            'day_of_week' => $bookingDate->isoWeekday(),
            'opens_at' => '09:00',
            'closes_at' => '18:00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->get(route('index', [
            'date' => $bookingDate->toDateString(),
        ]))
            ->assertOk()
            ->assertSee('Strzelnica testowa');

        $this->actingAs($admin)
            ->post(route('rezerwacje.store'), [
                'shooting_range_id' => $rangeId,
                'line_id' => $laneId,
                'date' => $bookingDate->toDateString(),
                'start_time' => '10:00',
                'end_time' => '10:30',
                'positions_count' => 1,
                'is_dynamic_shooting' => false,
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('reservations', [
            'shooting_range_id' => $rangeId,
            'line_id' => $laneId,
            'start' => $bookingDate->toDateString().' 10:00:00',
        ]);
    }

    public function test_list_and_public_page_allow_booking_when_range_is_closed_today(): void
    {
        [, $rangeId] = $this->createBookableRange('admin', true);
        $endpoint = 'strzelnica_testowa';

        DB::table('shooting_ranges')
            ->where('id', $rangeId)
            ->update(['endpoint' => $endpoint]);
        DB::table('shooting_range_opening_hours')
            ->where('shooting_range_id', $rangeId)
            ->where('day_of_week', today()->isoWeekday())
            ->delete();

        $this->get(route('shootings.list'))
            ->assertOk()
            ->assertSee('data-online="1"', false)
            ->assertSee(
                url('/rezerwacje/'.$rangeId).'?date='.today()->format('Y-m-d'),
                false
            );

        $view = app(ShootingsController::class)->publicShow($endpoint);

        $this->assertTrue($view->getData()['canReserve']);
        $this->assertSame(
            '',
            $view->getData()['reservationUnavailableReason']
        );
    }

    public function test_private_range_is_publicly_visible_with_closed_group_label(): void
    {
        [, $rangeId] = $this->createBookableRange('admin', false);
        $endpoint = 'prywatna_strzelnica';

        DB::table('shooting_ranges')
            ->where('id', $rangeId)
            ->update(['endpoint' => $endpoint]);

        $this->get(route('shootings.list'))
            ->assertOk()
            ->assertSee('Strzelnica testowa')
            ->assertSee('Online')
            ->assertSee('Grupa zamknięta');

        $this->get(route('index', [
            'date' => today()->toDateString(),
        ]))
            ->assertOk()
            ->assertSee('Strzelnica testowa')
            ->assertSee('Grupa zamknięta');

        $view = app(ShootingsController::class)->publicShow($endpoint);

        $this->assertTrue($view->getData()['canReserve']);
        $this->assertStringContainsString(
            'Rezerwacje dla zamkniętej grupy użytkowników',
            $view->render()
        );

        $this->get(route('rezerwacje.calendar', $rangeId))
            ->assertOk()
            ->assertSee('Zaloguj się, aby zarezerwować')
            ->assertSee('Nie masz konta?')
            ->assertSee(route('login'), false)
            ->assertSee(route('register'), false)
            ->assertDontSee('id="daySummary"', false);

        $client = User::factory()->create([
            'role' => 'klient',
            'is_approved' => true,
        ]);

        $this->actingAs($client)
            ->get(route('rezerwacje.calendar', $rangeId))
            ->assertOk()
            ->assertSee('Rezerwacje dla zamkniętej grupy')
            ->assertSee('Twoje konto nie posiada aktywnego dostępu.')
            ->assertSee(route('shootings.list'), false)
            ->assertDontSee('id="daySummary"', false);

        $this->actingAs($client)
            ->getJson(route('rezerwacje.availability', [
                'id' => $rangeId,
                'date' => today()->toDateString(),
            ]))
            ->assertForbidden();
    }

    public function test_expired_current_payment_due_date_blocks_new_booking(): void
    {
        [$admin, $rangeId, $laneId] = $this->createBookableRange('admin', true);

        DB::table('shooting_range_interval')
            ->where('shooting_range_id', $rangeId)
            ->update([
                'datePaid' => null,
                'payment_due_date' => today()->subDay(),
            ]);

        $this->get(route('index', [
            'date' => today()->toDateString(),
        ]))
            ->assertOk()
            ->assertDontSee('Strzelnica testowa');

        $this->actingAs($admin)
            ->post(route('rezerwacje.store'), [
                'shooting_range_id' => $rangeId,
                'line_id' => $laneId,
                'date' => today()->toDateString(),
                'start_time' => '10:00',
                'end_time' => '10:30',
                'positions_count' => 1,
                'is_dynamic_shooting' => false,
            ])
            ->assertSessionHas(
                'error',
                'Strzelnica nie posiada obecnie aktywnego przedziału lub upłynął termin płatności.'
            );

        $this->assertDatabaseMissing('reservations', [
            'shooting_range_id' => $rangeId,
        ]);
    }

    public function test_invitation_cannot_be_accepted_by_account_with_different_email(): void
    {
        [, $rangeId] = $this->createBookableRange('admin', false);
        $client = User::factory()->create([
            'email' => 'inne-konto@example.test',
            'role' => 'klient',
            'is_approved' => true,
        ]);
        DB::table('shooting_range_invitations')->insert([
            'shooting_range_id' => $rangeId,
            'email' => 'zaproszony@example.test',
            'token' => 'test-token',
            'status' => 'pending',
            'expires_at' => today()->addDays(2),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($client)
            ->get(route('shooting.invitations.accept', 'test-token'))
            ->assertForbidden();
    }

    public function test_staff_customer_search_finds_user_by_phone(): void
    {
        [$admin, $rangeId] = $this->createBookableRange('admin', true);
        $customer = User::factory()->create([
            'telephone' => '501 502 503',
            'role' => 'klient',
            'is_approved' => true,
        ]);

        $this->actingAs($admin)
            ->getJson(route('admin.reservations.customers.search', [
                'shooting_range_id' => $rangeId,
                'q' => '501 502',
            ]))
            ->assertOk()
            ->assertJsonPath('users.0.id', $customer->id)
            ->assertJsonPath('users.0.email', $customer->email);
    }

    public function test_staff_can_book_for_selected_existing_account(): void
    {
        [$admin, $rangeId, $laneId] = $this->createBookableRange('admin', true);
        $customer = User::factory()->create([
            'role' => 'klient',
            'is_approved' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.reservations.store'), [
                'shooting_range_id' => $rangeId,
                'line_id' => $laneId,
                'date' => today()->toDateString(),
                'start_time' => '16:00',
                'end_time' => '16:30',
                'positions_count' => 1,
                'customer_type' => 'account',
                'customer_user_id' => $customer->id,
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('reservations', [
            'customer_user_id' => $customer->id,
            'customer_email' => $customer->email,
            'source' => Reservation::SOURCE_STAFF_ACCOUNT,
            'status' => Reservation::STATUS_APPROVED,
        ]);
    }

    public function test_dynamic_booking_is_rejected_on_lane_without_dynamic_support(): void
    {
        [, $rangeId, $laneId] = $this->createBookableRange('admin', true);
        DB::table('lanes')
            ->where('id', $laneId)
            ->update(['allows_dynamic_shooting' => false]);
        $client = User::factory()->create([
            'role' => 'klient',
            'is_approved' => true,
        ]);

        $this->actingAs($client)
            ->post(route('rezerwacje.store'), [
                'shooting_range_id' => $rangeId,
                'line_id' => $laneId,
                'date' => today()->toDateString(),
                'start_time' => '17:00',
                'end_time' => '17:30',
                'positions_count' => 1,
                'is_dynamic_shooting' => true,
            ])
            ->assertSessionHas(
                'error',
                'Wybrana oś nie jest przystosowana do strzelania dynamicznego.'
            );
    }

    public function test_login_continues_pending_invitation_acceptance(): void
    {
        [, $rangeId] = $this->createBookableRange('admin', false);
        $client = User::factory()->create([
            'email' => 'zaproszony@example.test',
            'role' => 'klient',
            'is_approved' => true,
        ]);
        DB::table('shooting_range_invitations')->insert([
            'shooting_range_id' => $rangeId,
            'email' => $client->email,
            'token' => 'pending-login-token',
            'status' => 'pending',
            'expires_at' => today()->addDays(2),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withSession([
            'pending_invitation_token' => 'pending-login-token',
        ])->post('/login', [
            'email' => $client->email,
            'password' => 'password',
        ])->assertRedirect(route(
            'shooting.invitations.accept',
            'pending-login-token'
        ));

        $this->get(route(
            'shooting.invitations.accept',
            'pending-login-token'
        ))->assertRedirect(route('index'));

        $this->assertDatabaseHas('shooting_range_invitations', [
            'token' => 'pending-login-token',
            'user_id' => $client->id,
            'status' => 'accepted',
        ]);
    }

    public function test_lane_with_reservations_is_archived_instead_of_deleted(): void
    {
        [$admin, $rangeId, $laneId] = $this->createBookableRange('admin', true);
        DB::table('lanes')->insert([
            'shooting_range_id' => $rangeId,
            'name' => 'Oś zapasowa',
            'length' => 25,
            'numberOfPositions' => 1,
            'allows_dynamic_shooting' => false,
            'is_archived' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->insertReservation(
            $admin,
            $rangeId,
            $laneId,
            '15:00',
            '15:30',
            1,
            Reservation::STATUS_APPROVED
        );

        $this->actingAs($admin)
            ->delete(route('shooting.lanes.destroy', $laneId))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('lanes', [
            'id' => $laneId,
            'is_archived' => true,
        ]);
    }

    public function test_customer_can_edit_own_pending_future_reservation(): void
    {
        [, $rangeId, $laneId] = $this->createBookableRange('admin', true);
        $client = User::factory()->create([
            'role' => 'klient',
            'is_approved' => true,
        ]);
        $reservationId = $this->insertReservation(
            $client,
            $rangeId,
            $laneId,
            '10:00',
            '10:30',
            1,
            Reservation::STATUS_PENDING
        );

        $this->actingAs($client)
            ->patch(route('rezerwacje.update', $reservationId), [
                'shooting_range_id' => $rangeId,
                'line_id' => $laneId,
                'date' => today()->toDateString(),
                'start_time' => '11:00',
                'end_time' => '12:00',
                'positions_count' => 2,
                'is_dynamic_shooting' => false,
            ])
            ->assertRedirect(route('rezerwacje.mine'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('reservations', [
            'id' => $reservationId,
            'start' => today()->toDateString().' 11:00:00',
            'end' => today()->toDateString().' 12:00:00',
            'positions_count' => 2,
            'status' => Reservation::STATUS_PENDING,
        ]);
    }

    public function test_customer_can_open_own_reservations_list(): void
    {
        [, $rangeId, $laneId] = $this->createBookableRange('admin', true);
        $client = User::factory()->create([
            'role' => 'klient',
            'is_approved' => true,
        ]);
        $reservationId = $this->insertReservation(
            $client,
            $rangeId,
            $laneId,
            '10:00',
            '10:30',
            1,
            Reservation::STATUS_PENDING
        );

        $this->actingAs($client)
            ->get(route('rezerwacje.mine'))
            ->assertOk()
            ->assertSee('Moje rezerwacje')
            ->assertSee(route('rezerwacje.edit', $reservationId));
    }

    public function test_customer_history_contains_archived_reservations(): void
    {
        $client = User::factory()->create([
            'role' => 'klient',
            'is_approved' => true,
        ]);

        DB::table('reservation_archives')->insert([
            'original_reservation_id' => 987,
            'user_id' => $client->id,
            'customer_user_id' => $client->id,
            'customer_name' => $client->name,
            'customer_email' => $client->email,
            'source' => Reservation::SOURCE_ONLINE_ACCOUNT,
            'shooting_range_name' => 'Archiwalna strzelnica',
            'shooting_range_town' => 'Warszawa',
            'lane_name' => 'Oś historyczna',
            'start' => now()->subMonths(7)->setTime(10, 0),
            'end' => now()->subMonths(7)->setTime(10, 30),
            'positions_count' => 1,
            'is_dynamic_shooting' => false,
            'status' => Reservation::STATUS_COMPLETED,
            'archived_at' => now(),
        ]);

        $this->actingAs($client)
            ->get(route('rezerwacje.mine', ['view' => 'history']))
            ->assertOk()
            ->assertSee('Archiwalna strzelnica')
            ->assertSee('Oś historyczna')
            ->assertSee('Zrealizowana');
    }

    public function test_command_archives_final_reservations_and_purges_expired_history(): void
    {
        [, $rangeId, $laneId] = $this->createBookableRange('admin', true);
        $client = User::factory()->create([
            'role' => 'klient',
            'is_approved' => true,
        ]);
        $reservationId = $this->insertReservation(
            $client,
            $rangeId,
            $laneId,
            '10:00',
            '10:30',
            1,
            Reservation::STATUS_COMPLETED,
            false,
            today()->subMonths(7)
        );

        DB::table('reservation_archives')->insert([
            'original_reservation_id' => 123456,
            'start' => now()->subMonths(61)->setTime(10, 0),
            'end' => now()->subMonths(61)->setTime(10, 30),
            'status' => Reservation::STATUS_COMPLETED,
            'archived_at' => now()->subMonths(54),
        ]);

        $this->artisan('reservations:archive-and-purge')
            ->assertSuccessful();

        $this->assertDatabaseMissing('reservations', [
            'id' => $reservationId,
        ]);
        $this->assertDatabaseHas('reservation_archives', [
            'original_reservation_id' => $reservationId,
            'shooting_range_id' => $rangeId,
            'line_id' => $laneId,
        ]);
        $this->assertDatabaseMissing('reservation_archives', [
            'original_reservation_id' => 123456,
        ]);
    }

    public function test_staff_archive_only_contains_assigned_shooting_ranges(): void
    {
        [$admin, $ownRangeId] = $this->createBookableRange('admin', true);
        [, $otherRangeId] = $this->createBookableRange('admin', true);

        $this->insertArchivedReservation(
            $ownRangeId,
            'Archiwum własnej strzelnicy',
            'Klient własny'
        );
        $this->insertArchivedReservation(
            $otherRangeId,
            'Archiwum obcej strzelnicy',
            'Klient obcy'
        );

        $this->actingAs($admin)
            ->get(route('admin.reservations.archive'))
            ->assertOk()
            ->assertSee('Archiwum własnej strzelnicy')
            ->assertDontSee('Archiwum obcej strzelnicy');

        $this->actingAs($admin)
            ->get(route('admin.reservations.archive', [
                'shooting_range_id' => $otherRangeId,
            ]))
            ->assertForbidden();
    }

    public function test_superadmin_can_filter_all_archived_reservations(): void
    {
        [, $firstRangeId] = $this->createBookableRange('admin', true);
        [, $secondRangeId] = $this->createBookableRange('admin', true);
        $superadmin = User::factory()->create([
            'role' => 'superadmin',
            'is_approved' => true,
        ]);

        $this->insertArchivedReservation(
            $firstRangeId,
            'Pierwsza strzelnica archiwalna',
            'Jan Archiwalny'
        );
        $this->insertArchivedReservation(
            $secondRangeId,
            'Druga strzelnica archiwalna',
            'Anna Historyczna'
        );

        $this->actingAs($superadmin)
            ->get(route('admin.reservations.archive'))
            ->assertOk()
            ->assertSee('Pierwsza strzelnica archiwalna')
            ->assertSee('Druga strzelnica archiwalna')
            ->assertSee('data-date-picker-only', false)
            ->assertSee('max="'.today()->toDateString().'"', false);

        $this->actingAs($superadmin)
            ->get(route('admin.reservations.archive', [
                'q' => 'Anna Historyczna',
            ]))
            ->assertOk()
            ->assertSee('Anna Historyczna')
            ->assertDontSee('Jan Archiwalny');

        $this->actingAs($superadmin)
            ->get(route('admin.reservations.archive', [
                'date_from' => '999999.99.99',
            ]))
            ->assertSessionHasErrors('date_from');
    }

    public function test_deleted_shooting_range_filter_redirects_without_model_exception(): void
    {
        $this->createBookableRange('admin', true);
        $superadmin = User::factory()->create([
            'role' => 'superadmin',
            'is_approved' => true,
        ]);

        $missingRangeId = 407;

        $this->assertDatabaseMissing('shooting_ranges', [
            'id' => $missingRangeId,
        ]);

        $this->actingAs($superadmin)
            ->get(route('admin.reservations.calendar', [
                'shooting_range_id' => $missingRangeId,
            ]))
            ->assertRedirect(route('admin.reservations.calendar'))
            ->assertSessionHas('error');

        $this->actingAs($superadmin)
            ->get(route('admin.reservations.archive', [
                'shooting_range_id' => $missingRangeId,
            ]))
            ->assertRedirect(route('admin.reservations.archive'))
            ->assertSessionHas('error');
    }

    public function test_client_cannot_open_staff_reservation_archive(): void
    {
        $client = User::factory()->create([
            'role' => 'klient',
            'is_approved' => true,
        ]);

        $this->actingAs($client)
            ->get(route('admin.reservations.archive'))
            ->assertForbidden();
    }

    public function test_staff_account_can_access_its_own_customer_reservations(): void
    {
        [$admin, $rangeId, $laneId] = $this->createBookableRange('admin', true);
        $reservationId = $this->insertReservation(
            $admin,
            $rangeId,
            $laneId,
            '10:00',
            '10:30',
            1,
            Reservation::STATUS_PENDING
        );

        $this->actingAs($admin)
            ->get(route('rezerwacje.mine'))
            ->assertOk()
            ->assertSee('Moje rezerwacje')
            ->assertSee(route('rezerwacje.edit', $reservationId));
    }

    public function test_customer_cannot_edit_non_pending_or_started_reservation(): void
    {
        [, $rangeId, $laneId] = $this->createBookableRange('admin', true);
        $client = User::factory()->create([
            'role' => 'klient',
            'is_approved' => true,
        ]);
        $approvedId = $this->insertReservation(
            $client,
            $rangeId,
            $laneId,
            '10:00',
            '10:30',
            1,
            Reservation::STATUS_APPROVED
        );
        $startedId = $this->insertReservation(
            $client,
            $rangeId,
            $laneId,
            '07:00',
            '08:30',
            1,
            Reservation::STATUS_PENDING
        );

        $this->actingAs($client)
            ->get(route('rezerwacje.edit', $approvedId))
            ->assertForbidden();
        $this->actingAs($client)
            ->get(route('rezerwacje.edit', $startedId))
            ->assertForbidden();
    }

    public function test_staff_can_edit_all_future_reservation_parameters(): void
    {
        [$admin, $rangeId, $laneId] = $this->createBookableRange('admin', true);
        $client = User::factory()->create([
            'role' => 'klient',
            'is_approved' => true,
        ]);
        $reservationId = $this->insertReservation(
            $client,
            $rangeId,
            $laneId,
            '10:00',
            '10:30',
            1,
            Reservation::STATUS_APPROVED
        );
        $this->actingAs($admin)
            ->patch(route('admin.reservations.update', $reservationId), [
                'shooting_range_id' => $rangeId,
                'line_id' => $laneId,
                'date' => today()->toDateString(),
                'start_time' => '12:00',
                'end_time' => '13:00',
                'positions_count' => 2,
                'is_dynamic_shooting' => true,
                'customer_type' => 'guest',
                'customer_name' => 'Gość Edytowany',
                'customer_email' => 'gosc-edytowany@example.test',
                'customer_phone' => '600700800',
                'notes' => 'Zmieniona notatka',
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('reservations', [
            'id' => $reservationId,
            'customer_user_id' => null,
            'customer_name' => 'Gość Edytowany',
            'source' => Reservation::SOURCE_STAFF_GUEST,
            'start' => today()->toDateString().' 12:00:00',
            'end' => today()->toDateString().' 13:00:00',
            'positions_count' => 2,
            'is_dynamic_shooting' => true,
            'notes' => 'Zmieniona notatka',
        ]);
    }

    public function test_completion_status_is_available_only_after_reservation_ends(): void
    {
        [$admin, $rangeId, $laneId] = $this->createBookableRange('admin', true);
        $futureId = $this->insertReservation(
            $admin,
            $rangeId,
            $laneId,
            '10:00',
            '10:30',
            1,
            Reservation::STATUS_APPROVED
        );
        $startedId = $this->insertReservation(
            $admin,
            $rangeId,
            $laneId,
            '06:30',
            '07:30',
            1,
            Reservation::STATUS_APPROVED
        );
        $ongoingId = $this->insertReservation(
            $admin,
            $rangeId,
            $laneId,
            '07:30',
            '08:30',
            1,
            Reservation::STATUS_APPROVED
        );

        $this->actingAs($admin)
            ->patch(route('admin.reservations.status', $futureId), [
                'status' => Reservation::STATUS_COMPLETED,
            ])
            ->assertSessionHas(
                'error',
                'Status realizacji można ustawić dopiero po zakończeniu rezerwacji.'
            );

        $this->actingAs($admin)
            ->patch(route('admin.reservations.status', $ongoingId), [
                'status' => Reservation::STATUS_NO_SHOW,
            ])
            ->assertSessionHas(
                'error',
                'Status realizacji można ustawić dopiero po zakończeniu rezerwacji.'
            );

        $this->actingAs($admin)
            ->patch(route('admin.reservations.status', $startedId), [
                'status' => Reservation::STATUS_NO_SHOW,
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('reservations', [
            'id' => $startedId,
            'status' => Reservation::STATUS_NO_SHOW,
            'finalized_by_user_id' => $admin->id,
        ]);
        $this->assertNotNull(
            DB::table('reservations')
                ->where('id', $startedId)
                ->value('finalized_at')
        );
    }

    private function createBookableRange(
        string $staffRole,
        bool $isPublic
    ): array {
        $staff = User::factory()->create([
            'role' => $staffRole,
            'is_approved' => true,
        ]);
        $rangeId = DB::table('shooting_ranges')->insertGetId([
            'name' => 'Strzelnica testowa',
            'is_visible' => true,
            'is_public_access' => $isPublic,
            'is_archived' => false,
            'max_advance_booking_days' => 30,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('shooting_range_employee')->insert([
            'employee_id' => $staff->id,
            'shooting_ranges_id' => $rangeId,
            'role' => $staffRole,
            'is_approved' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($staffRole !== 'admin') {
            $administrator = User::factory()->create([
                'role' => 'admin',
                'is_approved' => true,
            ]);
            DB::table('shooting_range_employee')->insert([
                'employee_id' => $administrator->id,
                'shooting_ranges_id' => $rangeId,
                'role' => 'admin',
                'is_approved' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        $laneId = DB::table('lanes')->insertGetId([
            'shooting_range_id' => $rangeId,
            'name' => 'Oś 25 m',
            'length' => 25,
            'numberOfPositions' => 2,
            'allows_dynamic_shooting' => true,
            'is_archived' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('shooting_range_opening_hours')->insert([
            'shooting_range_id' => $rangeId,
            'day_of_week' => today()->isoWeekday(),
            'opens_at' => '09:00',
            'closes_at' => '18:00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('shooting_range_opening_hours')->insert([
            'shooting_range_id' => $rangeId,
            'day_of_week' => today()->addDay()->isoWeekday(),
            'opens_at' => '09:00',
            'closes_at' => '18:00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('shooting_range_interval')->insert([
            'shooting_range_id' => $rangeId,
            'start_date' => today()->subDay(),
            'end_date' => today()->addMonth(),
            'datePaid' => now(),
            'payment_due_date' => null,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$staff, $rangeId, $laneId];
    }

    private function insertReservation(
        User $customer,
        int $rangeId,
        int $laneId,
        string $startTime,
        string $endTime,
        int $positions,
        string $status,
        bool $dynamic = false,
        ?Carbon $date = null
    ): int {
        $date ??= today();

        return DB::table('reservations')->insertGetId([
            'user_id' => $customer->id,
            'mod_id' => $customer->id,
            'customer_user_id' => $customer->id,
            'customer_name' => $customer->name,
            'customer_email' => $customer->email,
            'customer_phone' => $customer->telephone,
            'created_by_user_id' => $customer->id,
            'source' => Reservation::SOURCE_ONLINE_ACCOUNT,
            'shooting_range_id' => $rangeId,
            'line_id' => $laneId,
            'start' => $date->toDateString().' '.$startTime,
            'end' => $date->toDateString().' '.$endTime,
            'positions_count' => $positions,
            'is_dynamic_shooting' => $dynamic,
            'status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertArchivedReservation(
        int $rangeId,
        string $rangeName,
        string $customerName
    ): int {
        return DB::table('reservation_archives')->insertGetId([
            'original_reservation_id' => (
                (int) DB::table('reservation_archives')
                    ->max('original_reservation_id')
            ) + 1,
            'customer_name' => $customerName,
            'customer_email' => mb_strtolower(
                str_replace(' ', '.', $customerName)
            ).'@example.com',
            'source' => Reservation::SOURCE_ONLINE_ACCOUNT,
            'shooting_range_id' => $rangeId,
            'shooting_range_name' => $rangeName,
            'shooting_range_town' => 'Warszawa',
            'lane_name' => 'Oś archiwalna',
            'lane_length' => 25,
            'start' => now()->subMonths(7)->setTime(10, 0),
            'end' => now()->subMonths(7)->setTime(10, 30),
            'positions_count' => 1,
            'is_dynamic_shooting' => false,
            'status' => Reservation::STATUS_COMPLETED,
            'archived_at' => now(),
        ]);
    }
}

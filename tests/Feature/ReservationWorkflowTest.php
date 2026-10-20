<?php

namespace Tests\Feature;

use App\Http\Controllers\ShootingsController;
use App\Mail\ReservationPaymentNotificationMail;
use App\Mail\ReservationTodayReminderMail;
use App\Models\Reservation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
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
            $table->boolean('has_shooting_leader_license')->default(false);
            $table->string('shooting_leader_license_number')->nullable();
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
            $table->string('street')->nullable();
            $table->string('houseNumber')->nullable();
            $table->string('apartamentNumber')->nullable();
            $table->string('zipCode')->nullable();
            $table->string('brand_file_name')->nullable();
            $table->boolean('is_visible')->default(true);
            $table->boolean('is_public_access')->default(true);
            $table->boolean('is_archived')->default(false);
            $table->unsignedSmallInteger('max_advance_booking_days')->nullable();
            $table->boolean('requires_reservation_confirmation')->default(true);
            $table->boolean('requires_shooting_leader')->default(false);
            $table->timestamps();
        });

        Schema::create('shooting_range_payment_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shooting_range_id')->unique();
            $table->boolean('p24_enabled')->default(false);
            $table->boolean('p24_sandbox')->default(true);
            $table->string('p24_merchant_id', 50)->nullable();
            $table->string('p24_pos_id', 50)->nullable();
            $table->string('p24_crc')->nullable();
            $table->string('p24_api_key')->nullable();
            $table->string('currency', 3)->default('PLN');
            $table->unsignedSmallInteger('reservation_payment_time_limit_minutes')->default(15);
            $table->string('status', 40)->default('not_configured');
            $table->dateTime('last_tested_at')->nullable();
            $table->boolean('last_test_success')->nullable();
            $table->text('last_test_message')->nullable();
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
            $table->boolean('receives_reservation_notifications')->default(false);
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
            $table->boolean('has_shooting_leader_license')->default(false);
            $table->string('shooting_leader_license_number')->nullable();
            $table->string('status');
            $table->boolean('payment_required')->default(false);
            $table->string('payment_status', 40)->default('not_required');
            $table->decimal('payment_amount', 10, 2)->nullable();
            $table->string('payment_currency', 3)->default('PLN');
            $table->dateTime('payment_expires_at')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->string('payment_method', 40)->nullable();
            $table->foreignId('approved_by_user_id')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('cancelled_by_user_id')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('finalized_by_user_id')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('today_reminder_sent_at')->nullable();
            $table->softDeletes();
            $table->foreignId('deleted_by_user_id')->nullable();
            $table->string('deletion_reason', 500)->nullable();
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
            $table->boolean('has_shooting_leader_license')->default(false);
            $table->string('shooting_leader_license_number')->nullable();
            $table->string('status');
            $table->boolean('payment_required')->default(false);
            $table->string('payment_status', 40)->default('not_required');
            $table->decimal('payment_amount', 10, 2)->nullable();
            $table->string('payment_currency', 3)->default('PLN');
            $table->dateTime('payment_expires_at')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->string('payment_method', 40)->nullable();
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

        Schema::create('reservation_price_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shooting_range_id');
            $table->foreignId('lane_id')->nullable();
            $table->string('name');
            $table->json('weekdays')->nullable();
            $table->time('time_from')->nullable();
            $table->time('time_to')->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->decimal('price_per_position_30min', 10, 2);
            $table->decimal('dynamic_price_per_position_30min', 10, 2)->nullable();
            $table->string('currency', 3)->default('PLN');
            $table->unsignedSmallInteger('priority')->default(100);
            $table->boolean('is_active')->default(true);
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('reservation_change_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id');
            $table->foreignId('requested_by_user_id');
            $table->foreignId('decided_by_user_id')->nullable();
            $table->string('type', 30);
            $table->string('status', 30)->default('pending');
            $table->foreignId('old_lane_id')->nullable();
            $table->foreignId('new_lane_id')->nullable();
            $table->dateTime('old_start')->nullable();
            $table->dateTime('old_end')->nullable();
            $table->dateTime('new_start')->nullable();
            $table->dateTime('new_end')->nullable();
            $table->unsignedInteger('old_positions_count')->nullable();
            $table->unsignedInteger('new_positions_count')->nullable();
            $table->boolean('old_is_dynamic_shooting')->nullable();
            $table->boolean('new_is_dynamic_shooting')->nullable();
            $table->boolean('old_has_shooting_leader_license')->nullable();
            $table->boolean('new_has_shooting_leader_license')->nullable();
            $table->string('old_shooting_leader_license_number', 100)->nullable();
            $table->string('new_shooting_leader_license_number', 100)->nullable();
            $table->text('customer_note')->nullable();
            $table->text('decision_note')->nullable();
            $table->dateTime('decided_at')->nullable();
            $table->timestamps();
        });

        Schema::create('reservation_price_promotions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shooting_range_id');
            $table->foreignId('lane_id')->nullable();
            $table->string('name');
            $table->string('discount_type', 20);
            $table->decimal('discount_value', 10, 2);
            $table->json('weekdays')->nullable();
            $table->time('time_from')->nullable();
            $table->time('time_to')->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->unsignedSmallInteger('priority')->default(100);
            $table->boolean('is_active')->default(true);
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->nullableMorphs('payable');
            $table->foreignId('package_order_id')->nullable();
            $table->string('provider', 40)->default('przelewy24');
            $table->string('method', 40)->default('p24');
            $table->string('status', 40)->default('created');
            $table->decimal('amount', 10, 2);
            $table->unsignedBigInteger('amount_minor');
            $table->unsignedBigInteger('refunded_amount_minor')->default(0);
            $table->unsignedBigInteger('refund_requested_amount_minor')->nullable();
            $table->string('currency', 3)->default('PLN');
            $table->string('description');
            $table->string('payer_email')->nullable();
            $table->string('payer_name')->nullable();
            $table->string('p24_session_id')->nullable()->unique();
            $table->string('p24_token')->nullable();
            $table->unsignedBigInteger('p24_order_id')->nullable();
            $table->unsignedInteger('p24_method')->nullable();
            $table->string('url_return')->nullable();
            $table->string('url_status')->nullable();
            $table->dateTime('registered_at')->nullable();
            $table->dateTime('verified_at')->nullable();
            $table->dateTime('failed_at')->nullable();
            $table->dateTime('refunded_at')->nullable();
            $table->string('refund_request_id', 45)->nullable();
            $table->string('refunds_uuid', 35)->nullable();
            $table->text('refund_error')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->foreignId('confirmed_by_user_id')->nullable();
            $table->dateTime('confirmed_at')->nullable();
            $table->text('confirmation_note')->nullable();
            $table->text('note')->nullable();
            $table->json('raw_register_response')->nullable();
            $table->json('raw_refund_response')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->nullable();
            $table->string('provider', 40)->default('przelewy24');
            $table->string('event_type', 80);
            $table->json('payload')->nullable();
            $table->boolean('signature_valid')->nullable();
            $table->dateTime('received_at')->nullable();
            $table->dateTime('processed_at')->nullable();
            $table->string('processing_status', 40)->default('received');
            $table->text('error_message')->nullable();
            $table->timestamps();
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

    public function test_guest_can_view_public_calendar_but_must_login_after_selecting_slot(): void
    {
        [, $rangeId] = $this->createBookableRange('admin', true);

        $this->get(route('rezerwacje.calendar', $rangeId))
            ->assertOk()
            ->assertSee('id="daySummary"', false)
            ->assertSee('id="lanesList"', false)
            ->assertSee('id="loginToReserveModal"', false)
            ->assertSee('Zaloguj się, aby dokończyć')
            ->assertSee(route('login'), false)
            ->assertSee(route('register'), false)
            ->assertDontSee('id="loginRequiredNotice"', false);

        $this->getJson(route('rezerwacje.availability', [
            'id' => $rangeId,
            'date' => today()->toDateString(),
        ]))
            ->assertOk()
            ->assertJsonStructure(['reservations']);
    }

    public function test_verified_client_returns_to_selected_reservation_slot_after_login(): void
    {
        [, $rangeId, $laneId] = $this->createBookableRange('admin', true);
        $client = User::factory()->create([
            'role' => 'klient',
            'email' => 'client@example.com',
            'email_verified_at' => now(),
            'password' => bcrypt('password'),
        ]);

        $this->post(route('rezerwacje.intent.store'), [
            'shooting_range_id' => $rangeId,
            'date' => today()->addDay()->toDateString(),
            'lane_id' => $laneId,
            'start_time' => '10:00',
            'end_time' => '10:30',
        ])->assertOk();

        $this->post('/login', [
            'email' => $client->email,
            'password' => 'password',
        ])->assertRedirect(
            route('rezerwacje.calendar', ['id' => $rangeId])
                .'?date='.today()->addDay()->toDateString()
                .'&lane='.$laneId
                .'&start=10%3A00'
        );

        $this->assertNull(session('reservation_intent'));
    }

    public function test_old_reservation_intent_is_ignored_after_login(): void
    {
        [, $rangeId, $laneId] = $this->createBookableRange('admin', true);
        $client = User::factory()->create([
            'role' => 'klient',
            'email' => 'client@example.com',
            'email_verified_at' => now(),
            'password' => bcrypt('password'),
        ]);

        $this->withSession([
            'reservation_intent' => [
                'shooting_range_id' => $rangeId,
                'date' => today()->addDay()->toDateString(),
                'lane_id' => $laneId,
                'start_time' => '10:00',
                'end_time' => '10:30',
                'created_at' => now()->subMinutes(31)->timestamp,
            ],
        ])->post('/login', [
            'email' => $client->email,
            'password' => 'password',
        ])->assertRedirect('/klient');

        $this->assertNull(session('reservation_intent'));
    }

    public function test_reservation_intent_is_cleared_when_user_leaves_auth_flow(): void
    {
        [, $rangeId, $laneId] = $this->createBookableRange('admin', true);

        $this->post(route('rezerwacje.intent.store'), [
            'shooting_range_id' => $rangeId,
            'date' => today()->addDay()->toDateString(),
            'lane_id' => $laneId,
            'start_time' => '10:00',
            'end_time' => '10:30',
        ])->assertOk();

        $this->get('/')
            ->assertOk()
            ->assertSessionMissing('reservation_intent');
    }

    public function test_customer_reservation_redirects_to_calendar_date_without_reopening_modal(): void
    {
        [, $rangeId, $laneId] = $this->createBookableRange('admin', true);
        $client = User::factory()->create([
            'role' => 'klient',
            'is_approved' => true,
            'email_verified_at' => now(),
        ]);
        $date = today()->addDay()->toDateString();

        $this->actingAs($client)
            ->from(route('rezerwacje.calendar', [
                'id' => $rangeId,
                'date' => $date,
                'lane' => $laneId,
                'start' => '10:00',
            ]))
            ->post(route('rezerwacje.store'), [
                'shooting_range_id' => $rangeId,
                'line_id' => $laneId,
                'date' => $date,
                'start_time' => '10:00',
                'end_time' => '10:30',
                'positions_count' => 1,
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success')
            ->assertRedirect(route('rezerwacje.calendar', [
                'id' => $rangeId,
                'date' => $date,
            ]));
    }

    public function test_customer_reservation_with_p24_creates_pending_payment_and_redirects_to_gateway(): void
    {
        Mail::fake();
        Http::fake([
            'sandbox.przelewy24.pl/api/v1/transaction/register' => Http::response([
                'data' => [
                    'token' => 'reservation-token',
                ],
            ]),
        ]);

        [, $rangeId, $laneId] = $this->createBookableRange('admin', true);
        $this->enableReservationPayments($rangeId, $laneId);
        $client = User::factory()->create([
            'role' => 'klient',
            'is_approved' => true,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($client)
            ->post(route('rezerwacje.store'), [
                'shooting_range_id' => $rangeId,
                'line_id' => $laneId,
                'date' => today()->toDateString(),
                'start_time' => '10:00',
                'end_time' => '11:00',
                'positions_count' => 2,
                'is_dynamic_shooting' => false,
            ])
            ->assertRedirect('https://sandbox.przelewy24.pl/trnRequest/reservation-token');

        $this->assertDatabaseHas('reservations', [
            'shooting_range_id' => $rangeId,
            'line_id' => $laneId,
            'status' => Reservation::STATUS_PENDING,
            'payment_required' => true,
            'payment_status' => Reservation::PAYMENT_PENDING,
            'payment_amount' => 100,
            'payment_method' => 'p24',
        ]);

        $reservationId = DB::table('reservations')->value('id');

        $this->assertDatabaseHas('payments', [
            'payable_type' => Reservation::class,
            'payable_id' => $reservationId,
            'provider' => 'przelewy24',
            'method' => 'p24',
            'status' => 'registered',
            'amount' => 100,
            'amount_minor' => 10000,
            'p24_token' => 'reservation-token',
        ]);
    }

    public function test_p24_callback_marks_reservation_payment_as_paid_and_approves_reservation(): void
    {
        Mail::fake();
        Http::fake([
            'sandbox.przelewy24.pl/api/v1/transaction/register' => Http::response([
                'data' => [
                    'token' => 'reservation-token',
                ],
            ]),
            'sandbox.przelewy24.pl/api/v1/transaction/verify' => Http::response([
                'data' => true,
            ]),
        ]);

        [, $rangeId, $laneId] = $this->createBookableRange('admin', true);
        $this->enableReservationPayments($rangeId, $laneId);
        $client = User::factory()->create([
            'role' => 'klient',
            'is_approved' => true,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($client)
            ->post(route('rezerwacje.store'), [
                'shooting_range_id' => $rangeId,
                'line_id' => $laneId,
                'date' => today()->toDateString(),
                'start_time' => '10:00',
                'end_time' => '10:30',
                'positions_count' => 1,
                'is_dynamic_shooting' => false,
            ])
            ->assertRedirect('https://sandbox.przelewy24.pl/trnRequest/reservation-token');

        $payment = DB::table('payments')->first();

        $this->post(route('payments.p24.status'), [
            'sessionId' => $payment->p24_session_id,
            'orderId' => 123456,
            'methodId' => 154,
        ])->assertOk();

        $this->assertDatabaseHas('reservations', [
            'id' => $payment->payable_id,
            'status' => Reservation::STATUS_APPROVED,
            'payment_status' => Reservation::PAYMENT_PAID,
        ]);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'paid',
            'p24_order_id' => 123456,
            'p24_method' => 154,
        ]);

        $this->assertDatabaseHas('payment_events', [
            'payment_id' => $payment->id,
            'processing_status' => 'processed',
        ]);
    }

    public function test_duplicate_p24_callback_does_not_send_payment_confirmation_again(): void
    {
        Mail::fake();
        Http::fake([
            'sandbox.przelewy24.pl/api/v1/transaction/register' => Http::response([
                'data' => [
                    'token' => 'reservation-token',
                ],
            ]),
            'sandbox.przelewy24.pl/api/v1/transaction/verify' => Http::response([
                'data' => true,
            ]),
        ]);

        [, $rangeId, $laneId] = $this->createBookableRange('admin', true);
        $this->enableReservationPayments($rangeId, $laneId);
        $client = User::factory()->create([
            'role' => 'klient',
            'is_approved' => true,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($client)
            ->post(route('rezerwacje.store'), [
                'shooting_range_id' => $rangeId,
                'line_id' => $laneId,
                'date' => today()->toDateString(),
                'start_time' => '10:00',
                'end_time' => '10:30',
                'positions_count' => 1,
                'is_dynamic_shooting' => false,
            ])
            ->assertRedirect('https://sandbox.przelewy24.pl/trnRequest/reservation-token');

        $payment = DB::table('payments')->first();

        $payload = [
            'sessionId' => $payment->p24_session_id,
            'orderId' => 123456,
            'methodId' => 154,
        ];

        $this->post(route('payments.p24.status'), $payload)->assertOk();
        $this->post(route('payments.p24.status'), $payload)->assertOk();

        Mail::assertSent(ReservationPaymentNotificationMail::class, 2);

        $this->assertDatabaseCount('payment_events', 2);
        $this->assertDatabaseHas('reservations', [
            'id' => $payment->payable_id,
            'status' => Reservation::STATUS_APPROVED,
            'payment_status' => Reservation::PAYMENT_PAID,
        ]);
    }

    public function test_reservation_can_end_at_midnight_when_range_closes_at_2359(): void
    {
        [, $rangeId, $laneId] = $this->createBookableRange('admin', true);

        DB::table('shooting_range_opening_hours')
            ->where('shooting_range_id', $rangeId)
            ->where('day_of_week', today()->isoWeekday())
            ->update([
                'opens_at' => '00:00',
                'closes_at' => '23:59',
            ]);

        $client = User::factory()->create([
            'role' => 'klient',
            'is_approved' => true,
        ]);

        $this->actingAs($client)
            ->post(route('rezerwacje.store'), [
                'shooting_range_id' => $rangeId,
                'line_id' => $laneId,
                'date' => today()->toDateString(),
                'start_time' => '23:30',
                'end_time' => '24:00',
                'positions_count' => 1,
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('reservations', [
            'shooting_range_id' => $rangeId,
            'line_id' => $laneId,
            'start' => today()->setTime(23, 30)->format('Y-m-d H:i:s'),
            'end' => today()->addDay()->startOfDay()->format('Y-m-d H:i:s'),
        ]);

        $this->getJson(route('rezerwacje.availability', [
            'id' => $rangeId,
            'date' => today()->toDateString(),
        ]))
            ->assertOk()
            ->assertJsonPath('reservations.0.end_time', '24:00');
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
            ->assertSee('Rezerwacje dla zamkniętej grupy')
            ->assertSee('Zaloguj się, aby sprawdzić, czy masz dostęp do rezerwacji.')
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

    public function test_invitation_list_can_be_searched_and_is_paginated(): void
    {
        [$admin, $rangeId] = $this->createBookableRange('admin', false);

        for ($number = 1; $number <= 25; $number++) {
            DB::table('shooting_range_invitations')->insert([
                'shooting_range_id' => $rangeId,
                'first_name' => $number === 25 ? 'Anna' : 'Klient',
                'last_name' => $number === 25 ? 'Kowalska' : "Numer {$number}",
                'email' => $number === 25
                    ? 'anna.kowalska@example.com'
                    : "klient{$number}@example.com",
                'status' => 'none',
                'invited_by' => $admin->id,
                'created_at' => now()->addSeconds($number),
                'updated_at' => now()->addSeconds($number),
            ]);
        }

        $firstPage = $this->actingAs($admin)
            ->get(route('shooting.invitations.index'))
            ->assertOk()
            ->assertSee('id="page"', false);

        $this->assertSame(20, $firstPage->viewData('invitations')->count());
        $this->assertSame(25, $firstPage->viewData('invitations')->total());

        $secondPage = $this->actingAs($admin)
            ->get(route('shooting.invitations.index', ['page' => 2]))
            ->assertOk();

        $this->assertSame(5, $secondPage->viewData('invitations')->count());

        $tenPerPage = $this->actingAs($admin)
            ->get(route('shooting.invitations.index', ['per_page' => 10]))
            ->assertOk();

        $this->assertSame(10, $tenPerPage->viewData('invitations')->count());
        $this->assertSame(10, $tenPerPage->viewData('perPage'));
        $this->assertSame(3, $tenPerPage->viewData('invitations')->lastPage());

        $fullNameSearch = $this->actingAs($admin)
            ->get(route('shooting.invitations.index', [
                'search' => 'Anna Kowalska',
            ]))
            ->assertOk()
            ->assertSee('anna.kowalska@example.com');

        $this->assertSame(
            1,
            $fullNameSearch->viewData('invitations')->total()
        );

        $emailSearch = $this->actingAs($admin)
            ->get(route('shooting.invitations.index', [
                'search' => 'anna.kowalska@',
            ]))
            ->assertOk();

        $this->assertSame(1, $emailSearch->viewData('invitations')->total());

        $emailAscending = $this->actingAs($admin)
            ->get(route('shooting.invitations.index', [
                'sort' => 'email',
                'direction' => 'asc',
                'per_page' => 10,
            ]))
            ->assertOk();

        $this->assertSame('email', $emailAscending->viewData('sort'));
        $this->assertSame('asc', $emailAscending->viewData('direction'));
        $this->assertSame(10, $emailAscending->viewData('perPage'));
        $this->assertSame(
            'anna.kowalska@example.com',
            $emailAscending->viewData('invitations')->first()->email
        );

        $emailDescending = $this->actingAs($admin)
            ->get(route('shooting.invitations.index', [
                'sort' => 'email',
                'direction' => 'desc',
            ]))
            ->assertOk();

        $this->assertSame(
            'klient9@example.com',
            $emailDescending->viewData('invitations')->first()->email
        );
    }

    public function test_existing_invitation_is_not_changed_and_is_shown_in_search(): void
    {
        [$admin, $rangeId] = $this->createBookableRange('admin', false);
        $invitationId = DB::table('shooting_range_invitations')->insertGetId([
            'shooting_range_id' => $rangeId,
            'first_name' => 'Anna',
            'last_name' => 'Kowalska',
            'email' => 'anna.kowalska@example.com',
            'status' => 'accepted',
            'accepted_at' => now()->subDay(),
            'invited_by' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)
            ->post(route('shooting.invitations.store'), [
                'first_name' => 'Zmienione',
                'last_name' => 'Dane',
                'email' => 'ANNA.KOWALSKA@EXAMPLE.COM',
                'expires_at' => now()->addMonth()->format('Y-m-d H:i:s'),
            ]);

        $response
            ->assertRedirect(route('shooting.invitations.index', [
                'search' => 'anna.kowalska@example.com',
            ]))
            ->assertSessionHas(
                'warning',
                'Użytkownik z adresem anna.kowalska@example.com jest już na liście. Nie wprowadzono zmian.'
            );

        $this->assertDatabaseHas('shooting_range_invitations', [
            'id' => $invitationId,
            'first_name' => 'Anna',
            'last_name' => 'Kowalska',
            'email' => 'anna.kowalska@example.com',
            'status' => 'accepted',
        ]);
        $this->assertSame(
            1,
            DB::table('shooting_range_invitations')
                ->where('shooting_range_id', $rangeId)
                ->count()
        );

        $filteredList = $this->actingAs($admin)
            ->get($response->headers->get('Location'))
            ->assertOk()
            ->assertSee('anna.kowalska@example.com');

        $this->assertSame(
            1,
            $filteredList->viewData('invitations')->total()
        );
    }

    public function test_new_and_imported_invitations_expire_after_seven_days(): void
    {
        [$admin, $rangeId] = $this->createBookableRange('admin', false);
        $expectedExpiry = now()->addDays(7)->format('Y-m-d H:i:s');

        $this->actingAs($admin)
            ->post(route('shooting.invitations.store'), [
                'first_name' => 'Jan',
                'last_name' => 'Nowy',
                'email' => 'jan.nowy@example.com',
                'expires_at' => now()->addMonth()->format('Y-m-d H:i:s'),
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('shooting_range_invitations', [
            'shooting_range_id' => $rangeId,
            'email' => 'jan.nowy@example.com',
            'expires_at' => $expectedExpiry,
        ]);

        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray([
            ['Imię', 'Nazwisko', 'Email'],
            ['Anna', 'Importowana', 'anna.importowana@example.com'],
        ]);
        $excelPath = tempnam(sys_get_temp_dir(), 'invitations').'.xlsx';
        (new Xlsx($spreadsheet))->save($excelPath);

        $this->actingAs($admin)
            ->post(route('shooting.invitations.import'), [
                'file' => new UploadedFile(
                    $excelPath,
                    'invitations.xlsx',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    null,
                    true
                ),
                'expires_at' => now()->addMonth()->format('Y-m-d H:i:s'),
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('shooting_range_invitations', [
            'shooting_range_id' => $rangeId,
            'email' => 'anna.importowana@example.com',
            'expires_at' => $expectedExpiry,
        ]);

        $this->actingAs($admin)
            ->get(route('shooting.invitations.template'))
            ->assertOk()
            ->assertDownload('szablon-zaproszen.xlsx');
    }

    public function test_generated_invitation_links_expire_after_seven_days(): void
    {
        [$admin, $rangeId] = $this->createBookableRange('admin', false);
        $expectedExpiry = now()->addDays(7)->format('Y-m-d H:i:s');

        $singleInvitationId = DB::table('shooting_range_invitations')
            ->insertGetId([
                'shooting_range_id' => $rangeId,
                'first_name' => 'Jan',
                'last_name' => 'Testowy',
                'email' => 'jan.testowy@example.com',
                'status' => 'none',
                'expires_at' => null,
                'invited_by' => $admin->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        $bulkInvitationId = DB::table('shooting_range_invitations')
            ->insertGetId([
                'shooting_range_id' => $rangeId,
                'first_name' => 'Anna',
                'last_name' => 'Testowa',
                'email' => 'anna.testowa@example.com',
                'status' => 'none',
                'expires_at' => now()->subDay(),
                'invited_by' => $admin->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        $this->actingAs($admin)
            ->patch(route('shooting.invitations.link', $singleInvitationId), [
                'expires_at' => now()->addMonth()->format('Y-m-d H:i:s'),
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('shooting_range_invitations', [
            'id' => $singleInvitationId,
            'status' => 'pending',
            'expires_at' => $expectedExpiry,
        ]);

        $this->actingAs($admin)
            ->post(route('shooting.invitations.bulk'), [
                'ids' => [$bulkInvitationId],
                'action' => 'generate',
                'expires_at' => now()->addMonth()->format('Y-m-d H:i:s'),
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('shooting_range_invitations', [
            'id' => $bulkInvitationId,
            'status' => 'pending',
            'expires_at' => $expectedExpiry,
        ]);
    }

    public function test_invitations_can_be_deleted_regardless_of_status(): void
    {
        [$admin, $rangeId] = $this->createBookableRange('admin', false);
        $invitationIds = [];

        foreach (['accepted', 'revoked', 'pending'] as $status) {
            $invitationIds[$status] = DB::table(
                'shooting_range_invitations'
            )->insertGetId([
                'shooting_range_id' => $rangeId,
                'first_name' => 'Klient',
                'last_name' => ucfirst($status),
                'email' => "{$status}@example.com",
                'status' => $status,
                'revoked_at' => $status === 'revoked' ? now() : null,
                'accepted_at' => $status === 'accepted' ? now() : null,
                'invited_by' => $admin->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->actingAs($admin)
            ->delete(route(
                'shooting.invitations.destroy',
                $invitationIds['accepted']
            ))
            ->assertRedirect(route('shooting.invitations.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('shooting_range_invitations', [
            'id' => $invitationIds['accepted'],
        ]);

        $this->actingAs($admin)
            ->post(route('shooting.invitations.bulk'), [
                'ids' => [
                    $invitationIds['revoked'],
                    $invitationIds['pending'],
                ],
                'action' => 'delete',
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('shooting_range_invitations', [
            'id' => $invitationIds['revoked'],
        ]);
        $this->assertDatabaseMissing('shooting_range_invitations', [
            'id' => $invitationIds['pending'],
        ]);
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
            ->assertSee('Brak strzelnic spełniających kryteria.');

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
            ->assertOk()
            ->assertSee('Zaproszenie jest przypisane do innego adresu e-mail')
            ->assertSee('Wróć na stronę główną');

        $this->assertDatabaseHas('shooting_range_invitations', [
            'token' => 'test-token',
            'status' => 'pending',
            'user_id' => null,
        ]);
    }

    public function test_missing_or_old_invitation_token_shows_dedicated_status_page(): void
    {
        $this->get(route('shooting.invitations.accept', 'missing-or-old-token'))
            ->assertOk()
            ->assertSee('Zaproszenie jest nieaktualne')
            ->assertSee('Wróć na stronę główną');
    }

    public function test_revoked_invitation_shows_dedicated_status_page(): void
    {
        [, $rangeId] = $this->createBookableRange('admin', false);
        DB::table('shooting_range_invitations')->insert([
            'shooting_range_id' => $rangeId,
            'email' => 'zaproszony@example.test',
            'token' => 'revoked-token',
            'status' => 'revoked',
            'expires_at' => today()->addDays(2),
            'revoked_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->get(route('shooting.invitations.accept', 'revoked-token'))
            ->assertOk()
            ->assertSee('Zaproszenie zostało odwołane')
            ->assertSee('Wróć na stronę główną');
    }

    public function test_expired_invitation_shows_dedicated_status_page_and_marks_invitation_expired(): void
    {
        [, $rangeId] = $this->createBookableRange('admin', false);
        DB::table('shooting_range_invitations')->insert([
            'shooting_range_id' => $rangeId,
            'email' => 'zaproszony@example.test',
            'token' => 'expired-token',
            'status' => 'pending',
            'expires_at' => today()->subDay(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->get(route('shooting.invitations.accept', 'expired-token'))
            ->assertOk()
            ->assertSee('Zaproszenie wygasło')
            ->assertSee('Wróć na stronę główną');

        $this->assertDatabaseHas('shooting_range_invitations', [
            'token' => 'expired-token',
            'status' => 'expired',
        ]);
    }

    public function test_guest_with_existing_account_is_sent_to_login_for_invitation_acceptance(): void
    {
        [, $rangeId] = $this->createBookableRange('admin', false);
        User::factory()->create([
            'email' => 'zaproszony@example.test',
            'role' => 'klient',
            'is_approved' => true,
        ]);
        DB::table('shooting_range_invitations')->insert([
            'shooting_range_id' => $rangeId,
            'email' => 'ZAPROSZONY@example.test',
            'token' => 'existing-account-token',
            'status' => 'pending',
            'expires_at' => today()->addDays(2),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->get(route('shooting.invitations.accept', 'existing-account-token'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('pending_invitation_token', 'existing-account-token')
            ->assertSessionHas('pending_invitation_email', 'zaproszony@example.test');
    }

    public function test_guest_without_account_is_sent_to_registration_for_invitation_acceptance(): void
    {
        [, $rangeId] = $this->createBookableRange('admin', false);
        DB::table('shooting_range_invitations')->insert([
            'shooting_range_id' => $rangeId,
            'email' => 'nowy@example.test',
            'token' => 'new-account-token',
            'status' => 'pending',
            'expires_at' => today()->addDays(2),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->get(route('shooting.invitations.accept', 'new-account-token'))
            ->assertRedirect(route('register'))
            ->assertSessionHas('pending_invitation_token', 'new-account-token')
            ->assertSessionHas('pending_invitation_email', 'nowy@example.test');
    }

    public function test_staff_customer_search_finds_user_by_email(): void
    {
        [$admin, $rangeId] = $this->createBookableRange('admin', true);
        $customer = User::factory()->create([
            'email' => 'klient.rezerwacji@example.test',
            'telephone' => '501 502 503',
            'role' => 'klient',
            'is_approved' => true,
        ]);

        $this->actingAs($admin)
            ->getJson(route('admin.reservations.customers.search', [
                'shooting_range_id' => $rangeId,
                'q' => 'KLIENT.REZERWACJI@example.test',
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

    public function test_login_from_empty_session_finds_pending_invitation_once(): void
    {
        [, $rangeId] = $this->createBookableRange('admin', false);
        $client = User::factory()->create([
            'email' => 'zaproszony@example.test',
            'role' => 'klient',
            'is_approved' => true,
        ]);
        DB::table('shooting_range_invitations')->insert([
            'shooting_range_id' => $rangeId,
            'email' => 'ZAPROSZONY@example.test',
            'token' => 'empty-session-token',
            'status' => 'pending',
            'expires_at' => today()->addDays(2),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->post('/login', [
            'email' => $client->email,
            'password' => 'password',
        ])->assertRedirect('/klient')
            ->assertSessionHas('pending_invitation_token', 'empty-session-token')
            ->assertSessionHas('pending_invitation_email', 'zaproszony@example.test');

        $this->assertDatabaseHas('shooting_range_invitations', [
            'token' => 'empty-session-token',
            'user_id' => null,
            'status' => 'pending',
        ]);

        $this->get(route('index'))
            ->assertOk()
            ->assertSee('Czeka zaproszenie do strzelnicy');
    }

    public function test_login_from_empty_session_finds_all_pending_invitations(): void
    {
        [, $firstRangeId] = $this->createBookableRange('admin', false);
        [, $secondRangeId] = $this->createBookableRange('admin2', false);
        $client = User::factory()->create([
            'email' => 'wiele-zaproszen@example.test',
            'role' => 'klient',
            'is_approved' => true,
        ]);

        DB::table('shooting_range_invitations')->insert([
            [
                'shooting_range_id' => $firstRangeId,
                'email' => $client->email,
                'token' => 'first-empty-session-token',
                'status' => 'pending',
                'expires_at' => today()->addDays(2),
                'created_at' => now(),
                'updated_at' => now()->subMinute(),
            ],
            [
                'shooting_range_id' => $secondRangeId,
                'email' => $client->email,
                'token' => 'second-empty-session-token',
                'status' => 'pending',
                'expires_at' => today()->addDays(2),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->post('/login', [
            'email' => $client->email,
            'password' => 'password',
        ])->assertRedirect('/klient')
            ->assertSessionHas('pending_invitations.0.token', 'second-empty-session-token')
            ->assertSessionHas('pending_invitations.1.token', 'first-empty-session-token');

        $this->get(route('index'))
            ->assertOk()
            ->assertSee('>2</span>', false)
            ->assertSee('first-empty-session-token')
            ->assertSee('second-empty-session-token');
    }

    public function test_invitation_link_login_opens_pending_invitation_modal(): void
    {
        [, $rangeId] = $this->createBookableRange('admin', false);
        User::factory()->create([
            'email' => 'modal@example.test',
            'role' => 'klient',
            'is_approved' => true,
        ]);
        DB::table('shooting_range_invitations')->insert([
            'shooting_range_id' => $rangeId,
            'email' => 'modal@example.test',
            'token' => 'modal-token',
            'status' => 'pending',
            'expires_at' => today()->addDays(2),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->get(route('shooting.invitations.accept', 'modal-token'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('pending_invitation_open_modal', true)
            ->assertSessionHas('pending_invitations.0.token', 'modal-token');
    }

    public function test_login_shows_pending_invitation_notification_without_accepting_automatically(): void
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
            'pending_invitation_email' => $client->email,
        ])->post('/login', [
            'email' => $client->email,
            'password' => 'password',
        ])->assertRedirect('/klient')
            ->assertSessionHas('pending_invitation_token', 'pending-login-token');

        $this->assertDatabaseHas('shooting_range_invitations', [
            'token' => 'pending-login-token',
            'user_id' => null,
            'status' => 'pending',
        ]);

        $this->get(route('index'))
            ->assertOk()
            ->assertSee('Czeka zaproszenie do strzelnicy');

        $this->get(route('shooting.invitations.accept', [
            'token' => 'pending-login-token',
            'confirm' => 1,
        ]))->assertRedirect(route('index'));

        $this->assertDatabaseHas('shooting_range_invitations', [
            'token' => 'pending-login-token',
            'user_id' => $client->id,
            'status' => 'accepted',
        ]);
    }

    public function test_logged_user_invitation_link_opens_modal_before_acceptance(): void
    {
        [, $rangeId] = $this->createBookableRange('admin', false);
        $client = User::factory()->create([
            'email' => 'logged-link@example.test',
            'role' => 'klient',
            'is_approved' => true,
        ]);
        DB::table('shooting_range_invitations')->insert([
            'shooting_range_id' => $rangeId,
            'email' => $client->email,
            'token' => 'logged-link-token',
            'status' => 'pending',
            'expires_at' => today()->addDays(2),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($client)
            ->get(route('shooting.invitations.accept', 'logged-link-token'))
            ->assertRedirect(route('index'))
            ->assertSessionHas('pending_invitation_open_modal', true)
            ->assertSessionHas('pending_invitations.0.token', 'logged-link-token');

        $this->assertDatabaseHas('shooting_range_invitations', [
            'token' => 'logged-link-token',
            'user_id' => null,
            'status' => 'pending',
        ]);
    }

    public function test_pending_invitation_notification_has_not_now_button_without_hiding_icon(): void
    {
        $client = User::factory()->create([
            'email' => 'zaproszony@example.test',
            'role' => 'klient',
            'is_approved' => true,
        ]);

        $this->actingAs($client)
            ->withSession([
                'pending_invitation_token' => 'pending-not-now-token',
                'pending_invitation_email' => $client->email,
            ]);

        $this->get(route('index'))
            ->assertOk()
            ->assertSee('Czeka zaproszenie do strzelnicy')
            ->assertSee('Nie teraz')
            ->assertSee('data-bs-dismiss="modal"', false);
    }

    public function test_pending_invitation_notification_can_be_declined(): void
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
            'token' => 'pending-decline-token',
            'status' => 'pending',
            'expires_at' => today()->addDays(2),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($client)
            ->withSession([
                'pending_invitation_token' => 'pending-decline-token',
                'pending_invitation_email' => $client->email,
            ])
            ->post(route('shooting.invitations.decline', 'pending-decline-token'))
            ->assertRedirect(route('index'))
            ->assertSessionMissing('pending_invitation_token');

        $this->assertDatabaseHas('shooting_range_invitations', [
            'token' => 'pending-decline-token',
            'status' => 'revoked',
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

    public function test_command_sends_today_reservation_reminders_once(): void
    {
        Mail::fake();
        [, $rangeId, $laneId] = $this->createBookableRange('admin', true);
        $customer = User::factory()->create([
            'email' => 'przypomnienie@example.test',
            'role' => 'klient',
            'is_approved' => true,
        ]);
        $tooLateCustomer = User::factory()->create([
            'email' => 'za-pozno@example.test',
            'role' => 'klient',
            'is_approved' => true,
        ]);

        $reservationId = $this->insertReservation(
            $customer,
            $rangeId,
            $laneId,
            '15:00',
            '15:30',
            1,
            Reservation::STATUS_APPROVED,
            false,
            today(),
            today()->setTime(13, 30)->subDay()
        );
        $this->insertReservation(
            $tooLateCustomer,
            $rangeId,
            $laneId,
            '16:00',
            '16:30',
            1,
            Reservation::STATUS_APPROVED,
            false,
            today(),
            today()->setTime(17, 0)->subDay()
        );
        $this->insertReservation(
            $customer,
            $rangeId,
            $laneId,
            '10:00',
            '10:30',
            1,
            Reservation::STATUS_APPROVED,
            false,
            today()->addDay(),
            today()->subDays(2)
        );
        $this->insertReservation(
            $customer,
            $rangeId,
            $laneId,
            '11:00',
            '11:30',
            1,
            Reservation::STATUS_CANCELLED,
            false,
            today(),
            today()->subDays(2)
        );
        $this->insertReservation(
            $customer,
            $rangeId,
            $laneId,
            '12:00',
            '12:30',
            1,
            Reservation::STATUS_APPROVED,
            false,
            today(),
            today()->subDays(2),
            now()
        );

        $this->artisan('reservations:send-today-reminders')
            ->expectsOutput('Reservation reminders sent: 1')
            ->assertSuccessful();

        Mail::assertSent(
            ReservationTodayReminderMail::class,
            fn (ReservationTodayReminderMail $mail): bool => $mail->hasTo($customer->email)
                && $mail->reservation->id === $reservationId
        );
        Mail::assertNotSent(
            ReservationTodayReminderMail::class,
            fn (ReservationTodayReminderMail $mail): bool => $mail->hasTo($tooLateCustomer->email)
        );
        $this->assertNotNull(
            DB::table('reservations')
                ->where('id', $reservationId)
                ->value('today_reminder_sent_at')
        );

        $this->artisan('reservations:send-today-reminders')
            ->expectsOutput('Reservation reminders sent: 0')
            ->assertSuccessful();
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

    public function test_customer_can_request_change_for_approved_future_reservation_but_not_started_reservation(): void
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
            ->assertOk();

        $this->actingAs($client)
            ->patch(route('rezerwacje.update', $approvedId), [
                'shooting_range_id' => $rangeId,
                'line_id' => $laneId,
                'date' => today()->toDateString(),
                'start_time' => '11:00',
                'end_time' => '11:30',
                'positions_count' => 1,
                'is_dynamic_shooting' => false,
            ])
            ->assertRedirect(route('rezerwacje.mine'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('reservations', [
            'id' => $approvedId,
            'status' => Reservation::STATUS_APPROVED,
            'approved_by_user_id' => null,
            'approved_at' => null,
        ]);
        $this->assertDatabaseHas('reservation_change_requests', [
            'reservation_id' => $approvedId,
            'type' => 'change',
            'status' => 'pending',
            'old_lane_id' => $laneId,
            'new_lane_id' => $laneId,
        ]);

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

    public function test_admin_can_hide_and_restore_unpaid_reservation_without_deleting_history(): void
    {
        Mail::fake();
        [$admin, $rangeId, $laneId] = $this->createBookableRange('admin', true);
        $client = User::factory()->create([
            'role' => 'klient',
            'is_approved' => true,
        ]);
        $reservationId = $this->insertReservation(
            $client,
            $rangeId,
            $laneId,
            '12:00',
            '12:30',
            1,
            Reservation::STATUS_PENDING
        );

        $this->actingAs($admin)
            ->delete(route('admin.reservations.destroy', $reservationId), [
                'deletion_reason' => 'Omyłkowo utworzony duplikat.',
            ])
            ->assertSessionHas('success');

        $this->assertSoftDeleted('reservations', ['id' => $reservationId]);
        $this->assertDatabaseHas('reservations', [
            'id' => $reservationId,
            'status' => Reservation::STATUS_CANCELLED,
            'deleted_by_user_id' => $admin->id,
            'deletion_reason' => 'Omyłkowo utworzony duplikat.',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.reservations.hidden', [
                'shooting_range_id' => $rangeId,
            ]))
            ->assertOk()
            ->assertSee('Omyłkowo utworzony duplikat.');

        $this->actingAs($admin)
            ->patch(route('admin.reservations.hidden.restore', $reservationId))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('reservations', [
            'id' => $reservationId,
            'status' => Reservation::STATUS_CANCELLED,
            'deleted_at' => null,
            'deleted_by_user_id' => null,
            'deletion_reason' => null,
        ]);
    }

    public function test_employee_cannot_hide_reservation_or_view_hidden_reservations(): void
    {
        [$employee, $rangeId, $laneId] = $this->createBookableRange('pracownik', true);
        $client = User::factory()->create([
            'role' => 'klient',
            'is_approved' => true,
        ]);
        $reservationId = $this->insertReservation(
            $client,
            $rangeId,
            $laneId,
            '12:00',
            '12:30',
            1,
            Reservation::STATUS_PENDING
        );

        $this->actingAs($employee)
            ->delete(route('admin.reservations.destroy', $reservationId), [
                'deletion_reason' => 'Próba ukrycia wpisu.',
            ])
            ->assertForbidden();
        $this->actingAs($employee)
            ->get(route('admin.reservations.hidden'))
            ->assertForbidden();

        $this->assertDatabaseHas('reservations', [
            'id' => $reservationId,
            'deleted_at' => null,
        ]);
    }

    public function test_staff_cancellation_marks_manual_payment_for_refund(): void
    {
        Mail::fake();
        [$admin, $rangeId, $laneId] = $this->createBookableRange('admin', true);
        $client = User::factory()->create([
            'role' => 'klient',
            'is_approved' => true,
        ]);
        $reservationId = $this->insertReservation(
            $client,
            $rangeId,
            $laneId,
            '12:00',
            '12:30',
            1,
            Reservation::STATUS_APPROVED
        );
        DB::table('reservations')->where('id', $reservationId)->update([
            'payment_required' => true,
            'payment_status' => Reservation::PAYMENT_PAID,
            'payment_amount' => 50,
            'payment_currency' => 'PLN',
            'payment_method' => 'cash',
            'paid_at' => now(),
        ]);
        $paymentId = DB::table('payments')->insertGetId([
            'payable_type' => Reservation::class,
            'payable_id' => $reservationId,
            'provider' => 'manual',
            'method' => 'cash',
            'status' => 'paid',
            'amount' => 50,
            'amount_minor' => 5000,
            'currency' => 'PLN',
            'description' => 'Rezerwacja testowa',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.reservations.status', $reservationId), [
                'status' => Reservation::STATUS_CANCELLED,
                'cancellation_reason' => 'Odwołanie przez strzelnicę.',
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('reservations', [
            'id' => $reservationId,
            'status' => Reservation::STATUS_CANCELLED,
            'payment_status' => Reservation::PAYMENT_PAID,
        ]);
        $this->assertDatabaseHas('payments', [
            'id' => $paymentId,
            'status' => 'refund_pending',
            'refund_requested_amount_minor' => 5000,
        ]);

        $this->actingAs($admin)
            ->patch(route('shooting.reservation-payments.payments.confirm-refund', $paymentId), [
                'shooting_range_id' => $rangeId,
                'refunded_amount' => '50.00',
                'refunded_at' => now()->format('Y-m-d H:i:s'),
                'confirmation_note' => 'Zwrot gotówki potwierdzony przez administratora.',
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('payments', [
            'id' => $paymentId,
            'status' => 'refunded',
            'refunded_amount_minor' => 5000,
            'refund_requested_amount_minor' => null,
        ]);
        $this->assertDatabaseHas('reservations', [
            'id' => $reservationId,
            'payment_status' => Reservation::PAYMENT_REFUNDED,
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

    private function enableReservationPayments(int $rangeId, int $laneId): void
    {
        DB::table('shooting_range_payment_accounts')->insert([
            'shooting_range_id' => $rangeId,
            'p24_enabled' => true,
            'p24_sandbox' => true,
            'p24_merchant_id' => '420421',
            'p24_pos_id' => '420421',
            'p24_crc' => 'test-crc',
            'p24_api_key' => 'test-api-key',
            'currency' => 'PLN',
            'reservation_payment_time_limit_minutes' => 15,
            'status' => 'configured',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('reservation_price_rules')->insert([
            'shooting_range_id' => $rangeId,
            'lane_id' => $laneId,
            'name' => 'Cennik testowy',
            'price_per_position_30min' => 25,
            'dynamic_price_per_position_30min' => 40,
            'currency' => 'PLN',
            'priority' => 10,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
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
        ?Carbon $date = null,
        ?Carbon $createdAt = null,
        ?Carbon $todayReminderSentAt = null
    ): int {
        $date ??= today();
        $createdAt ??= now();

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
            'today_reminder_sent_at' => $todayReminderSentAt,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
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

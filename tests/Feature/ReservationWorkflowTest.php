<?php

namespace Tests\Feature;

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
            $table->boolean('is_visible')->default(true);
            $table->boolean('is_public_access')->default(true);
            $table->boolean('is_archived')->default(false);
            $table->unsignedSmallInteger('max_advance_booking_days')->nullable();
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
            $table->string('email');
            $table->string('status');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
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
            $table->string('status');
            $table->foreignId('approved_by_user_id')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('cancelled_by_user_id')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        DB::table('system_configurations')->insert([
            'default_max_advance_booking_days' => 30,
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
}

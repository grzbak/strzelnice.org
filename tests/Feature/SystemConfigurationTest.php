<?php

namespace Tests\Feature;

use App\Models\SystemConfiguration;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SystemConfigurationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

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
            $table->string('name')->nullable();
            $table->boolean('is_visible')->default(true);
            $table->boolean('is_archived')->default(false);
            $table->unsignedSmallInteger('max_advance_booking_days')->nullable();
            $table->timestamps();
        });

        Schema::create('shooting_range_employee', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id');
            $table->foreignId('shooting_ranges_id');
            $table->string('role')->nullable();
            $table->boolean('is_approved')->default(false);
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
            $table->timestamps();
        });

        SystemConfiguration::create(['is_archived' => false]);
    }

    public function test_only_superadmin_can_manage_system_configuration(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_approved' => true,
        ]);

        $superadmin = User::factory()->create([
            'role' => 'superadmin',
            'is_approved' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.system-configurations.index'))
            ->assertForbidden();

        $this->actingAs($superadmin)
            ->get(route('admin.system-configurations.index'))
            ->assertOk();
    }

    public function test_update_archives_current_record_and_creates_new_active_version(): void
    {
        $superadmin = User::factory()->create([
            'role' => 'superadmin',
            'is_approved' => true,
        ]);

        $current = SystemConfiguration::query()->active()->firstOrFail();

        $this->actingAs($superadmin)
            ->patch(route('admin.system-configurations.update', $current), [
                'company_name_1' => 'Strzelnice Test',
                'email' => 'kontakt@example.com',
                'postal_code' => '00-001',
                'default_max_advance_booking_days' => 30,
                'reservation_operational_months' => 6,
                'reservation_history_months' => 60,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('system_configurations', [
            'id' => $current->id,
            'is_archived' => true,
        ]);

        $this->assertDatabaseHas('system_configurations', [
            'company_name_1' => 'Strzelnice Test',
            'email' => 'kontakt@example.com',
            'reservation_operational_months' => 6,
            'reservation_history_months' => 60,
            'is_archived' => false,
        ]);

        $this->assertSame(1, SystemConfiguration::query()->active()->count());
    }

    public function test_operational_period_cannot_exceed_history_period(): void
    {
        $superadmin = User::factory()->create([
            'role' => 'superadmin',
            'is_approved' => true,
        ]);
        $current = SystemConfiguration::query()->active()->firstOrFail();

        $this->actingAs($superadmin)
            ->patch(route('admin.system-configurations.update', $current), [
                'default_max_advance_booking_days' => 30,
                'reservation_operational_months' => 12,
                'reservation_history_months' => 6,
            ])
            ->assertSessionHasErrors('reservation_operational_months');

        $this->assertSame(1, SystemConfiguration::query()->active()->count());
    }

    public function test_restore_creates_new_active_copy_of_archived_record(): void
    {
        $superadmin = User::factory()->create([
            'role' => 'superadmin',
            'is_approved' => true,
        ]);

        $archived = SystemConfiguration::create([
            'company_name_1' => 'Wersja archiwalna',
            'is_archived' => true,
        ]);

        $this->actingAs($superadmin)
            ->patch(route('admin.system-configurations.restore', $archived))
            ->assertRedirect();

        $this->assertSame(1, SystemConfiguration::query()->active()->count());
        $this->assertDatabaseHas('system_configurations', [
            'company_name_1' => 'Wersja archiwalna',
            'is_archived' => false,
        ]);
    }

    public function test_bank_account_is_normalized_validated_and_formatted(): void
    {
        $superadmin = User::factory()->create([
            'role' => 'superadmin',
            'is_approved' => true,
        ]);

        $current = SystemConfiguration::query()->active()->firstOrFail();

        $this->actingAs($superadmin)
            ->patch(route('admin.system-configurations.update', $current), [
                'bank_account_number' => 'PL61 1090 1014 0000 0712 1981 2874',
                'default_max_advance_booking_days' => 30,
                'reservation_operational_months' => 6,
                'reservation_history_months' => 60,
            ])
            ->assertRedirect();

        $configuration = SystemConfiguration::query()->active()->firstOrFail();

        $this->assertSame(
            '61109010140000071219812874',
            $configuration->bank_account_number
        );
        $this->assertSame(
            '61 1090 1014 0000 0712 1981 2874',
            $configuration->formatted_bank_account_number
        );

        $formattedAccount = '61 1090 1014 0000 0712 1981 2874';

        $this->actingAs($superadmin)
            ->get(route('admin.system-configurations.edit', $configuration))
            ->assertOk()
            ->assertSee($formattedAccount);

        $this->actingAs($superadmin)
            ->get(route('admin.system-configurations.show', $configuration))
            ->assertOk()
            ->assertSee($formattedAccount);

        $this->get(route('contact'))
            ->assertOk()
            ->assertSee($formattedAccount);

        $this->actingAs($superadmin)
            ->patch(route('admin.system-configurations.update', $configuration), [
                'bank_account_number' => '61 1090 1014 0000 0712 1981 2875',
                'default_max_advance_booking_days' => 30,
                'reservation_operational_months' => 6,
                'reservation_history_months' => 60,
            ])
            ->assertSessionHasErrors('bank_account_number');

        $this->assertSame(1, SystemConfiguration::query()->active()->count());
    }

    public function test_shooting_range_uses_global_limit_or_its_own_override(): void
    {
        SystemConfiguration::query()->active()->update([
            'default_max_advance_booking_days' => 45,
        ]);

        $shootingRange = new \App\Models\ShootingRange([
            'max_advance_booking_days' => null,
        ]);

        $this->assertSame(45, $shootingRange->effectiveMaxAdvanceBookingDays());
        $this->assertTrue($shootingRange->allowsReservationDate(today()->addDays(45)));
        $this->assertFalse($shootingRange->allowsReservationDate(today()->addDays(46)));

        $shootingRange->max_advance_booking_days = 7;

        $this->assertSame(7, $shootingRange->effectiveMaxAdvanceBookingDays());
        $this->assertTrue($shootingRange->allowsReservationDate(today()->addDays(7)));
        $this->assertFalse($shootingRange->allowsReservationDate(today()->addDays(8)));
    }

    public function test_reservation_endpoint_rejects_date_beyond_shooting_range_limit(): void
    {
        $user = User::factory()->create([
            'role' => 'klient',
            'is_approved' => true,
        ]);

        $shootingRangeId = DB::table('shooting_ranges')->insertGetId([
            'name' => 'Strzelnica testowa',
            'is_visible' => true,
            'is_archived' => false,
            'max_advance_booking_days' => 7,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('shooting_range_employee')->insert([
            'employee_id' => $user->id,
            'shooting_ranges_id' => $shootingRangeId,
            'role' => 'admin',
            'is_approved' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $laneId = DB::table('lanes')->insertGetId([
            'shooting_range_id' => $shootingRangeId,
            'name' => 'Oś testowa',
            'length' => 25,
            'numberOfPositions' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)
            ->post(route('rezerwacje.store'), [
                'shooting_range_id' => $shootingRangeId,
                'line_id' => $laneId,
                'date' => today()->addDays(8)->toDateString(),
                'start_time' => '10:00',
                'end_time' => '10:30',
                'positions_count' => 1,
            ])
            ->assertSessionHas(
                'error',
                'Rezerwacji można dokonać maksymalnie 7 dni do przodu.'
            );
    }
}

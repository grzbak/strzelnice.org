<?php

namespace Tests\Feature;

use App\Mail\ShootingRangeStaffRoleMail;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ShootingRangeStaffManagementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

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

        Schema::create('shooting_ranges', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_visible')->default(true);
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

        Schema::create('shooting_range_employee', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id');
            $table->foreignId('shooting_ranges_id');
            $table->string('role');
            $table->boolean('is_approved')->default(true);
            $table->boolean('receives_reservation_notifications')->default(false);
            $table->timestamps();
        });
    }

    public function test_superadmin_can_add_change_and_remove_any_staff_role(): void
    {
        $superadmin = $this->createUser('superadmin', 'superadmin@example.test');
        $candidate = $this->createUser('klient', 'jan.kowalski@example.test');
        $secondAdmin = $this->createUser('klient', 'second.admin@example.test');
        $rangeId = $this->createRange();

        $this->actingAs($superadmin)
            ->post(route('shooting.staff.store', $rangeId), [
                'user_id' => $candidate->id,
                'role' => 'admin',
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('shooting_range_employee', [
            'shooting_ranges_id' => $rangeId,
            'employee_id' => $candidate->id,
            'role' => 'admin',
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $candidate->id,
            'role' => 'admin',
        ]);
        Mail::assertSent(
            ShootingRangeStaffRoleMail::class,
            fn (ShootingRangeStaffRoleMail $mail) => $mail->hasTo(
                $candidate->email
            )
                && $mail->staffRole === 'admin'
                && $mail->changeType
                    === ShootingRangeStaffRoleMail::CHANGE_ASSIGNED
        );

        $this->actingAs($superadmin)
            ->post(route('shooting.staff.store', $rangeId), [
                'user_id' => $secondAdmin->id,
                'role' => 'admin',
            ])
            ->assertSessionHas('success');

        $this->actingAs($superadmin)
            ->patch(route('shooting.staff.update-role', [
                $rangeId,
                $candidate->id,
            ]), [
                'role' => 'pracownik',
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('shooting_range_employee', [
            'shooting_ranges_id' => $rangeId,
            'employee_id' => $candidate->id,
            'role' => 'pracownik',
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $candidate->id,
            'role' => 'pracownik',
        ]);
        Mail::assertSent(
            ShootingRangeStaffRoleMail::class,
            fn (ShootingRangeStaffRoleMail $mail) => $mail->hasTo(
                $candidate->email
            )
                && $mail->staffRole === 'pracownik'
                && $mail->changeType
                    === ShootingRangeStaffRoleMail::CHANGE_ROLE_UPDATED
        );

        $this->actingAs($superadmin)
            ->delete(route('shooting.staff.destroy', [
                $rangeId,
                $candidate->id,
            ]))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('shooting_range_employee', [
            'shooting_ranges_id' => $rangeId,
            'employee_id' => $candidate->id,
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $candidate->id,
            'role' => 'klient',
        ]);
    }

    public function test_range_admin_can_search_add_and_remove_only_employees(): void
    {
        $admin = $this->createUser('admin', 'admin@example.test');
        $candidate = $this->createUser('klient', 'anna.nowak@example.test');
        $otherCandidate = $this->createUser(
            'klient',
            'ewa.kowalska@example.test'
        );
        $otherAdmin = $this->createUser('admin', 'other.admin@example.test');
        $rangeId = $this->createRange();
        $this->attach($rangeId, $admin->id, 'admin');
        $this->attach($rangeId, $otherAdmin->id, 'admin');

        $this->actingAs($admin)
            ->get(route('shooting.staff.index', $rangeId))
            ->assertOk()
            ->assertSee('Personel')
            ->assertSee('Dodaj pracownika');

        $this->actingAs($admin)
            ->getJson(route('shooting.staff.search', [
                'shootingRange' => $rangeId,
                'q' => 'anna',
            ]))
            ->assertOk()
            ->assertJsonPath('users.0.id', $candidate->id);

        $this->actingAs($admin)
            ->post(route('shooting.staff.store', $rangeId), [
                'user_id' => $candidate->id,
                'role' => 'pracownik',
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'id' => $candidate->id,
            'role' => 'pracownik',
        ]);

        $this->actingAs($admin)
            ->post(route('shooting.staff.store', $rangeId), [
                'user_id' => $otherCandidate->id,
                'role' => 'admin',
            ])
            ->assertForbidden();

        $this->actingAs($admin)
            ->patch(route('shooting.staff.update-role', [
                $rangeId,
                $candidate->id,
            ]), [
                'role' => 'admin',
            ])
            ->assertForbidden();

        $this->actingAs($admin)
            ->delete(route('shooting.staff.destroy', [
                $rangeId,
                $otherAdmin->id,
            ]))
            ->assertForbidden();

        $this->actingAs($admin)
            ->delete(route('shooting.staff.destroy', [
                $rangeId,
                $candidate->id,
            ]))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('shooting_range_employee', [
            'shooting_ranges_id' => $rangeId,
            'employee_id' => $candidate->id,
        ]);
    }

    public function test_staff_index_supports_table_search_sorting_and_page_size(): void
    {
        $superadmin = $this->createUser('superadmin', 'table.root@example.test');
        $rangeId = $this->createRange();

        for ($number = 1; $number <= 12; $number++) {
            $user = $this->createUser(
                'pracownik',
                "staff{$number}@example.test"
            );
            $user->update([
                'name' => $number === 12 ? 'Anna Sortowana' : "Personel {$number}",
            ]);
            $this->attach(
                $rangeId,
                $user->id,
                $number === 1 ? 'admin' : 'pracownik'
            );
        }

        $tenPerPage = $this->actingAs($superadmin)
            ->get(route('shooting.staff.index', [
                'shootingRange' => $rangeId,
                'per_page' => 10,
            ]))
            ->assertOk()
            ->assertSee('staff-table-search-form');

        $this->assertSame(10, $tenPerPage->viewData('staff')->count());
        $this->assertSame(10, $tenPerPage->viewData('perPage'));
        $this->assertSame(2, $tenPerPage->viewData('staff')->lastPage());

        $search = $this->actingAs($superadmin)
            ->get(route('shooting.staff.index', [
                'shootingRange' => $rangeId,
                'search' => 'Anna Sortowana',
            ]))
            ->assertOk()
            ->assertSee('Anna Sortowana');

        $this->assertSame(1, $search->viewData('staff')->total());

        $emailDescending = $this->actingAs($superadmin)
            ->get(route('shooting.staff.index', [
                'shootingRange' => $rangeId,
                'sort' => 'email',
                'direction' => 'desc',
                'per_page' => 10,
            ]))
            ->assertOk();

        $this->assertSame('email', $emailDescending->viewData('sort'));
        $this->assertSame('desc', $emailDescending->viewData('direction'));
        $this->assertSame(10, $emailDescending->viewData('perPage'));
        $this->assertSame(
            'staff9@example.test',
            $emailDescending->viewData('staff')->first()->email
        );
    }

    public function test_employee_cannot_manage_range_staff(): void
    {
        $employee = $this->createUser('pracownik', 'employee@example.test');
        $rangeId = $this->createRange();
        $this->attach($rangeId, $employee->id, 'pracownik');

        $this->actingAs($employee)
            ->get(route('shooting.staff.index', $rangeId))
            ->assertForbidden();
    }

    public function test_worker_requires_admin_and_last_admin_cannot_be_demoted(): void
    {
        $superadmin = $this->createUser('superadmin', 'root.guard@example.test');
        $administrator = $this->createUser('klient', 'admin.guard@example.test');
        $worker = $this->createUser('klient', 'worker.guard@example.test');
        $secondAdministrator = $this->createUser(
            'klient',
            'second.guard@example.test'
        );
        $rangeId = $this->createRange();

        $this->actingAs($superadmin)
            ->post(route('shooting.staff.store', $rangeId), [
                'user_id' => $worker->id,
                'role' => 'pracownik',
            ])
            ->assertSessionHas(
                'error',
                'Nie można dodać pracownika, ponieważ strzelnica nie ma '
                .'aktywnego administratora. Najpierw przypisz administratora.'
            );

        $this->assertDatabaseMissing('shooting_range_employee', [
            'shooting_ranges_id' => $rangeId,
            'employee_id' => $worker->id,
        ]);
        Mail::assertNotSent(
            ShootingRangeStaffRoleMail::class,
            fn (ShootingRangeStaffRoleMail $mail) => $mail->hasTo(
                $worker->email
            )
        );

        $this->actingAs($superadmin)
            ->post(route('shooting.staff.store', $rangeId), [
                'user_id' => $administrator->id,
                'role' => 'admin',
            ])
            ->assertSessionHas('success');

        $this->actingAs($superadmin)
            ->patch(route('shooting.staff.update-role', [
                $rangeId,
                $administrator->id,
            ]), [
                'role' => 'pracownik',
            ])
            ->assertSessionHas(
                'error',
                'Nie można zmienić ostatniego administratora na pracownika. '
                .'Najpierw przypisz drugiego aktywnego administratora.'
            );

        $this->assertDatabaseHas('shooting_range_employee', [
            'shooting_ranges_id' => $rangeId,
            'employee_id' => $administrator->id,
            'role' => 'admin',
        ]);

        $this->actingAs($superadmin)
            ->post(route('shooting.staff.store', $rangeId), [
                'user_id' => $secondAdministrator->id,
                'role' => 'admin',
            ])
            ->assertSessionHas('success');

        $this->actingAs($superadmin)
            ->patch(route('shooting.staff.update-role', [
                $rangeId,
                $administrator->id,
            ]), [
                'role' => 'pracownik',
            ])
            ->assertSessionHas('success');
    }

    public function test_user_cannot_be_assigned_to_more_than_one_range(): void
    {
        $superadmin = $this->createUser('superadmin', 'root@example.test');
        $candidate = $this->createUser('klient', 'candidate@example.test');
        $administrator = $this->createUser(
            'klient',
            'collision.admin@example.test'
        );
        $firstRangeId = $this->createRange('Pierwsza strzelnica');
        $secondRangeId = $this->createRange('Druga strzelnica');

        $this->actingAs($superadmin)
            ->post(route('shooting.staff.store', $firstRangeId), [
                'user_id' => $administrator->id,
                'role' => 'admin',
            ])
            ->assertSessionHas('success');

        $this->actingAs($superadmin)
            ->post(route('shooting.staff.store', $firstRangeId), [
                'user_id' => $candidate->id,
                'role' => 'pracownik',
            ])
            ->assertSessionHas('success');

        $this->actingAs($superadmin)
            ->getJson(route('shooting.staff.search', [
                'shootingRange' => $secondRangeId,
                'q' => 'candidate',
            ]))
            ->assertOk()
            ->assertJsonCount(0, 'users');

        $this->actingAs($superadmin)
            ->post(route('shooting.staff.store', $secondRangeId), [
                'user_id' => $candidate->id,
                'role' => 'admin',
            ])
            ->assertSessionHas(
                'error',
                'Użytkownik '.$candidate->name
                .' jest już przypisany do strzelnicy Pierwsza strzelnica. '
                .'Jeden użytkownik może pracować tylko w jednej strzelnicy.'
            );

        $this->assertDatabaseHas('shooting_range_employee', [
            'shooting_ranges_id' => $firstRangeId,
            'employee_id' => $candidate->id,
            'role' => 'pracownik',
        ]);
        $this->assertDatabaseMissing('shooting_range_employee', [
            'shooting_ranges_id' => $secondRangeId,
            'employee_id' => $candidate->id,
        ]);
    }

    private function createUser(string $role, string $email): User
    {
        return User::factory()->create([
            'email' => $email,
            'email_verified_at' => now(),
            'role' => $role,
            'is_approved' => true,
        ]);
    }

    private function createRange(string $name = 'Strzelnica testowa'): int
    {
        return DB::table('shooting_ranges')->insertGetId([
            'name' => $name,
            'is_visible' => true,
            'is_archived' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function attach(int $rangeId, int $userId, string $role): void
    {
        DB::table('shooting_range_employee')->insert([
            'shooting_ranges_id' => $rangeId,
            'employee_id' => $userId,
            'role' => $role,
            'is_approved' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

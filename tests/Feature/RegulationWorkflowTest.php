<?php

namespace Tests\Feature;

use App\Mail\RegulationUpdatedMail;
use App\Models\Page;
use App\Models\Regulation;
use App\Models\RegulationVersion;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RegulationWorkflowTest extends TestCase
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
            $table->string('endpoint')->nullable();
            $table->timestamps();
        });

        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('navigation_name')->unique();
            $table->string('display_name');
            $table->longText('content')->nullable();
            $table->string('baner')->nullable();
            $table->string('background')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $migration = include database_path('migrations/2026_07_12_120000_create_regulations_tables.php');
        $migration->up();
    }

    public function test_required_regulation_blocks_reservation_write_but_can_be_accepted(): void
    {
        [$user, $regulation] = $this->createRequiredRegulationForClient();

        $this->actingAs($user)
            ->post(route('rezerwacje.store'))
            ->assertRedirect(route('regulations.required'));

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk();

        $this->actingAs($user)
            ->post(route('regulations.required.accept'), [
                'accepted' => [$regulation->id],
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('regulation_acceptances', [
            'user_id' => $user->id,
            'regulation_id' => $regulation->id,
            'regulation_version_id' => $regulation->currentVersion->id,
        ]);
    }

    public function test_page_update_creates_new_regulation_version_only_when_content_changed(): void
    {
        Mail::fake();

        [$user, $regulation, $page] = $this->createRequiredRegulationForClient();
        $superadmin = User::factory()->create([
            'role' => 'superadmin',
            'is_approved' => true,
            'email_verified_at' => now(),
        ]);

        $user->regulationAcceptances()->create([
            'regulation_id' => $regulation->id,
            'regulation_version_id' => $regulation->currentVersion->id,
            'accepted_at' => now(),
        ]);

        $this->actingAs($superadmin)
            ->patch(route('admin.pages.update', $page), [
                'display_name' => $page->display_name,
                'content' => $page->content,
                'is_active' => '1',
            ])
            ->assertSessionHas('success');

        $this->assertSame(1, $regulation->versions()->count());
        Mail::assertNotSent(RegulationUpdatedMail::class);

        $this->actingAs($superadmin)
            ->patch(route('admin.pages.update', $page), [
                'display_name' => $page->display_name,
                'content' => '<p>Nowa treść regulaminu</p>',
                'is_active' => '1',
            ])
            ->assertSessionHas('success');

        $this->assertSame(2, $regulation->versions()->count());
        Mail::assertSent(RegulationUpdatedMail::class);
    }

    public function test_regulation_edit_shows_version_history_with_acceptance_count(): void
    {
        [$user, $regulation] = $this->createRequiredRegulationForClient();
        $superadmin = User::factory()->create([
            'role' => 'superadmin',
            'is_approved' => true,
            'email_verified_at' => now(),
        ]);

        $user->regulationAcceptances()->create([
            'regulation_id' => $regulation->id,
            'regulation_version_id' => $regulation->currentVersion->id,
            'accepted_at' => now(),
        ]);

        $this->actingAs($superadmin)
            ->get(route('admin.regulations.edit', $regulation))
            ->assertOk()
            ->assertSee('Historia wersji')
            ->assertSee('Wersja v1')
            ->assertSee('Akceptacje: 1');
    }

    private function createRequiredRegulationForClient(): array
    {
        $user = User::factory()->create([
            'role' => 'klient',
            'is_approved' => true,
            'email_verified_at' => now(),
        ]);

        $page = Page::create([
            'display_name' => 'Regulamin klienta',
            'content' => '<p>Treść regulaminu</p>',
            'is_active' => true,
        ]);

        $regulation = Regulation::create([
            'page_id' => $page->id,
            'name' => 'Regulamin klienta',
            'target_roles' => ['klient'],
            'starts_at' => today(),
            'is_indefinite' => true,
            'status' => Regulation::STATUS_ACTIVE,
        ]);

        $regulation->versions()->create([
            'page_id' => $page->id,
            'version_number' => 1,
            'display_name' => $page->display_name,
            'content' => $page->content,
            'content_hash' => RegulationVersion::contentHash($page->display_name, $page->content),
            'published_at' => now(),
        ]);

        return [$user, $regulation->fresh('currentVersion'), $page];
    }
}

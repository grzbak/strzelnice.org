<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Models\UserSecurityEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response([
                'success' => true,
                'score' => 0.9,
                'action' => 'kontakt',
            ]),
        ]);

        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'g-recaptcha-response' => 'test-token',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_registration_records_first_user_ip_address(): void
    {
        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response([
                'success' => true,
                'score' => 0.9,
                'action' => 'kontakt',
            ]),
        ]);

        $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test-ip@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'g-recaptcha-response' => 'test-token',
        ]);

        $user = User::where('email', 'test-ip@example.com')->firstOrFail();

        $this->assertDatabaseHas('user_ip_addresses', [
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'first_action' => 'register',
        ]);
    }

    public function test_registration_from_banned_ip_waits_for_superadmin_approval(): void
    {
        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response([
                'success' => true,
                'score' => 0.9,
                'action' => 'kontakt',
            ]),
        ]);

        UserSecurityEvent::create([
            'ip_address' => '127.0.0.1',
            'event_type' => UserSecurityEvent::TYPE_BAN,
            'severity' => UserSecurityEvent::SEVERITY_CRITICAL,
            'reason' => 'Testowy ban IP.',
        ]);

        $response = $this->post('/register', [
            'name' => 'Pending User',
            'email' => 'pending@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'g-recaptcha-response' => 'test-token',
        ]);

        $user = User::where('email', 'pending@example.com')->firstOrFail();

        $this->assertAuthenticatedAs($user);
        $this->assertFalse($user->is_approved);
        $this->assertSame(User::APPROVAL_PENDING, $user->approval_status);
        $response->assertRedirect(route('account.pending-approval'));
        $this->assertDatabaseHas('user_security_events', [
            'user_id' => $user->id,
            'event_type' => UserSecurityEvent::TYPE_SUSPICIOUS_REGISTRATION,
        ]);
    }
}

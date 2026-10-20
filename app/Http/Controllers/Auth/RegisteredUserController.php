<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\UserSecurityService;
use App\Support\PendingInvitationSession;
use App\Support\ReservationIntentSession;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        $admins = User::where('role', 'superadmin')->get();
        $role = 'klient';
        if ($admins->count() == 0) {
            $role = 'superadmin';
        }

        return view('auth.register', compact('role'));
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request, UserSecurityService $security): RedirectResponse
    {
        // dd($request);
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'telephone' => ['nullable', 'string', 'max:50'],
            'has_shooting_leader_license' => ['nullable', 'boolean'],
            'shooting_leader_license_number' => [
                'nullable',
                'required_if:has_shooting_leader_license,1',
                'string',
                'max:100',
            ],
            'g-recaptcha-response' => 'required',
        ], [
            'shooting_leader_license_number.required_if' => 'Podaj numer uprawnień lub legitymacji prowadzącego strzelanie.',
            'g-recaptcha-response.required' => 'Weryfikacja reCAPTCHA jest wymagana.',
        ]);

        $response = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
            'secret' => config('services.recaptcha.secret_key'),
            'response' => $request->input('g-recaptcha-response'),
            'remoteip' => $request->ip(),
        ]);

        $recaptcha = $response->json();

        if (
            empty($recaptcha['success']) ||
            ($recaptcha['score'] ?? 0) < 0.5 ||
            ($recaptcha['action'] ?? '') !== 'kontakt'
        ) {
            return back()
                ->withErrors(['g-recaptcha-response' => 'Weryfikacja reCAPTCHA nie powiodła się.'])
                ->withInput();
        }

        $requiresManualApproval = $security->requiresManualApproval($request);

        $userData = [
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'telephone' => $request->telephone,
            'has_shooting_leader_license' => $request->boolean(
                'has_shooting_leader_license'
            ),
            'shooting_leader_license_number' => $request->boolean(
                'has_shooting_leader_license'
            )
                ? $request->input('shooting_leader_license_number')
                : null,
            'role' => $request->input('role', 'klient'),
            'is_approved' => ! $requiresManualApproval && $request->boolean('is_approved', true),
        ];

        if (Schema::hasColumn('users', 'approval_status')) {
            $userData += [
                'approval_status' => $requiresManualApproval
                ? User::APPROVAL_PENDING
                : User::APPROVAL_APPROVED,
                'approval_reason' => $requiresManualApproval
                ? 'Rejestracja wymaga dodatkowej weryfikacji, ponieważ adres IP występuje w historii zdarzeń bezpieczeństwa.'
                : null,
                'approved_at' => $requiresManualApproval ? null : now(),
            ];
        }

        $user = User::create($userData);

        $security->recordIpAddress($user, $request, 'register');
        $security->audit('user.registered', $user, $request, $user);

        if ($requiresManualApproval) {
            $security->markSuspiciousRegistration(
                $user,
                $request,
                'Rejestracja z adresu IP powiązanego z aktywnym banem.'
            );
        }

        if ($user->is_approved) {
            event(new Registered($user));
        }

        Auth::login($user);
        PendingInvitationSession::syncForUser($request, $user);

        if (! $user->is_approved) {
            return redirect()->route('account.pending-approval');
        }

        if (ReservationIntentSession::redirectUrl($request) && ! $user->hasVerifiedEmail()) {
            return redirect()->route('verification.notice');
        }

        return redirect(route('dashboard', absolute: false));
    }
}

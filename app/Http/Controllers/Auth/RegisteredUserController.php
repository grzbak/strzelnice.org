<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\PendingInvitationSession;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
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
    public function store(Request $request): RedirectResponse
    {
        // dd($request);
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'g-recaptcha-response' => 'required',
        ], [
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

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'telephone' => $request->telephone,
            'role' => $request->input('role', 'klient'),
            'is_approved' => $request->input('is_approved', true),
        ]);

        event(new Registered($user));

        Auth::login($user);
        PendingInvitationSession::syncForUser($request, $user);

        return redirect(route('dashboard', absolute: false));
    }
}

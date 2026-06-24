<?php

namespace App\Http\Controllers;

use App\Mail\ShootingRangeInvitationMail;
use App\Models\ShootingRangeInvitation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ShootingRangeInvitationController extends Controller
{
    private function userShootingRange()
    {
        return Auth::user()->shootingRanges()
            ->wherePivot('is_approved', true)
            ->firstOrFail();
    }

    public function index()
    {
        $shootingRange = $this->userShootingRange();

        $invitations = $shootingRange->invitations()
            ->orderByDesc('created_at')
            ->get();

        return view('admin.shooting_ranges.invitations.index', compact(
            'shootingRange',
            'invitations'
        ));
    }

    public function store(Request $request)
    {
        $shootingRange = $this->userShootingRange();

        $validated = $request->validate([
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'expires_at' => ['nullable', 'date'],
        ]);

        $shootingRange->invitations()->updateOrCreate(
            ['email' => $validated['email']],
            [
                'first_name' => $validated['first_name'] ?? null,
                'last_name' => $validated['last_name'] ?? null,
                'expires_at' => $validated['expires_at'] ?? null,
                'status' => ShootingRangeInvitation::STATUS_NONE,
                'invited_by' => auth()->id(),
            ]
        );

        return back()->with('success', 'Dodano zaproszenie.');
    }

    public function import(Request $request)
    {
        $shootingRange = $this->userShootingRange();

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt'],
            'expires_at' => ['nullable', 'date'],
        ]);

        $handle = fopen($request->file('file')->getRealPath(), 'r');

        while (($row = fgetcsv($handle, 1000, ',')) !== false) {
            [$firstName, $lastName, $email] = array_pad($row, 3, null);

            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            $shootingRange->invitations()->updateOrCreate(
                ['email' => $email],
                [
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'expires_at' => $request->expires_at,
                    'status' => ShootingRangeInvitation::STATUS_NONE,
                    'invited_by' => auth()->id(),
                ]
            );
        }

        fclose($handle);

        return back()->with('success', 'Zaimportowano zaproszenia.');
    }

    public function generateLink(ShootingRangeInvitation $invitation)
    {
        $this->authorizeInvitation($invitation);

        $invitation->update([
            'token' => $invitation->token ?: Str::random(64),
            'status' => ShootingRangeInvitation::STATUS_PENDING,
        ]);

        return back()->with('success', 'Wygenerowano link zaproszenia.');
    }

    public function send(ShootingRangeInvitation $invitation)
    {
        $this->authorizeInvitation($invitation);

        if (! $invitation->token) {
            $invitation->update([
                'token' => Str::random(64),
                'status' => ShootingRangeInvitation::STATUS_PENDING,
            ]);
        }

        Mail::to($invitation->email)->send(new ShootingRangeInvitationMail($invitation));

        return back()->with('success', 'Wysłano zaproszenie.');
    }

    public function revoke(ShootingRangeInvitation $invitation)
    {
        $this->authorizeInvitation($invitation);

        $invitation->update([
            'status' => ShootingRangeInvitation::STATUS_REVOKED,
            'revoked_at' => now(),
        ]);

        return back()->with('success', 'Odwołano zaproszenie.');
    }

    public function bulk(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
            'action' => ['required', 'in:generate,send,revoke'],
        ]);

        $invitations = ShootingRangeInvitation::whereIn('id', $validated['ids'])->get();

        foreach ($invitations as $invitation) {
            $this->authorizeInvitation($invitation);

            if ($validated['action'] === 'generate') {
                $invitation->update([
                    'token' => $invitation->token ?: Str::random(64),
                    'status' => ShootingRangeInvitation::STATUS_PENDING,
                ]);
            }

            if ($validated['action'] === 'send') {
                if (! $invitation->token) {
                    $invitation->update([
                        'token' => Str::random(64),
                        'status' => ShootingRangeInvitation::STATUS_PENDING,
                    ]);
                }

                Mail::to($invitation->email)->send(new ShootingRangeInvitationMail($invitation));
            }

            if ($validated['action'] === 'revoke') {
                $invitation->update([
                    'status' => ShootingRangeInvitation::STATUS_REVOKED,
                    'revoked_at' => now(),
                ]);
            }
        }

        return back()->with('success', 'Wykonano operację zbiorczą.');
    }

    public function accept(string $token)
    {
        $invitation = ShootingRangeInvitation::where('token', $token)->firstOrFail();

        if ($invitation->status === ShootingRangeInvitation::STATUS_REVOKED) {
            abort(403, 'Zaproszenie zostało odwołane.');
        }

        if ($invitation->expires_at && $invitation->expires_at->isPast()) {
            $invitation->update([
                'status' => ShootingRangeInvitation::STATUS_EXPIRED,
            ]);

            abort(403, 'Zaproszenie wygasło.');
        }

        if (! auth()->check()) {
            session(['pending_invitation_token' => $token]);

            return redirect()->route('login')
                ->with('success', 'Zaloguj się, aby zaakceptować zaproszenie.');
        }

        $invitation->update([
            'status' => ShootingRangeInvitation::STATUS_ACCEPTED,
            'accepted_at' => now(),
            'user_id' => auth()->id(),
        ]);

        return redirect()->route('index')
            ->with('success', 'Zaproszenie zaakceptowane.');
    }

    private function authorizeInvitation(ShootingRangeInvitation $invitation): void
    {
        $shootingRange = $this->userShootingRange();

        abort_if($invitation->shooting_range_id !== $shootingRange->id, 403);
    }
}

<?php

namespace App\Http\Controllers;

use App\Mail\ShootingRangeInvitationMail;
use App\Mail\ShootingRangeInvitationRevokedMail;
use App\Models\ShootingRangeInvitation;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ShootingRangeInvitationController extends Controller
{
    private const RENEWED_INVITATION_DAYS = 7;

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
                'expires_soon_notified_at' => null,
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
        $imported = 0;

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
                    'expires_soon_notified_at' => null,
                    'status' => ShootingRangeInvitation::STATUS_NONE,
                    'invited_by' => auth()->id(),
                ]
            );

            $imported++;
        }

        fclose($handle);

        return back()->with('success', "Zaimportowano zaproszenia: {$imported}.");
    }

    public function generateLink(Request $request, ShootingRangeInvitation $invitation)
    {
        $this->authorizeInvitation($invitation);

        $validated = $request->validate([
            'expires_at' => ['nullable', 'date', 'after:now'],
        ], [], [
            'expires_at' => 'data ważności',
        ]);

        if ($invitation->isAccepted()) {
            return back()->with('error', 'Nie można wygenerować nowego linku dla zaakceptowanego zaproszenia.');
        }

        $renewedExpiry = $this->refreshInvitationLink($invitation, $validated['expires_at'] ?? null);

        $message = 'Wygenerowano nowy link zaproszenia.';

        if (! empty($validated['expires_at'])) {
            $message .= ' Ustawiono wybraną datę ważności.';
        } elseif ($renewedExpiry) {
            $message .= ' Poprzednia data ważności minęła, dlatego ustawiono ważność nowego linku na 7 dni.';
        }

        return back()->with('success', $message);
    }

    public function send(ShootingRangeInvitation $invitation)
    {
        $this->authorizeInvitation($invitation);

        if ($invitation->isAccepted()) {
            return back()->with('error', 'Zaproszenie zostało już zaakceptowane. Nie wysłano nowej wiadomości.');
        }

        $this->refreshInvitationLink($invitation);
        Mail::to($invitation->email)->send(new ShootingRangeInvitationMail($invitation->fresh('shootingRange')));

        return back()->with('success', 'Wysłano zaproszenie do klienta.');
    }

    public function revoke(ShootingRangeInvitation $invitation)
    {
        $this->authorizeInvitation($invitation);

        if ($invitation->isRevoked()) {
            return back()->with('error', 'Zaproszenie jest już odwołane.');
        }

        $invitation->update([
            'status' => ShootingRangeInvitation::STATUS_REVOKED,
            'revoked_at' => now(),
        ]);

        Mail::to($invitation->email)->send(new ShootingRangeInvitationRevokedMail($invitation->fresh('shootingRange')));

        return back()->with('success', 'Odwołano zaproszenie i wysłano informację do klienta.');
    }

    public function bulk(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
            'action' => ['required', 'in:generate,send,revoke'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ], [], [
            'expires_at' => 'data ważności',
        ]);

        $invitations = ShootingRangeInvitation::whereIn('id', $validated['ids'])->get();
        $done = 0;
        $skipped = 0;

        foreach ($invitations as $invitation) {
            $this->authorizeInvitation($invitation);

            if ($validated['action'] === 'generate') {
                if ($invitation->isAccepted()) {
                    $skipped++;
                    continue;
                }

                $this->refreshInvitationLink($invitation, $validated['expires_at'] ?? null);
                $done++;
            }

            if ($validated['action'] === 'send') {
                if ($invitation->isAccepted()) {
                    $skipped++;
                    continue;
                }

                $this->refreshInvitationLink($invitation, $validated['expires_at'] ?? null);
                Mail::to($invitation->email)->send(new ShootingRangeInvitationMail($invitation->fresh('shootingRange')));
                $done++;
            }

            if ($validated['action'] === 'revoke') {
                if ($invitation->isRevoked()) {
                    $skipped++;
                    continue;
                }

                $invitation->update([
                    'status' => ShootingRangeInvitation::STATUS_REVOKED,
                    'revoked_at' => now(),
                ]);

                Mail::to($invitation->email)->send(new ShootingRangeInvitationRevokedMail($invitation->fresh('shootingRange')));
                $done++;
            }
        }

        $message = "Wykonano operację zbiorczą. Przetworzono: {$done}.";

        if ($skipped > 0) {
            $message .= " Pominięto: {$skipped}.";
        }

        return back()->with('success', $message);
    }

    public function accept(string $token)
    {
        $invitation = ShootingRangeInvitation::where('token', $token)->firstOrFail();

        if ($invitation->isRevoked()) {
            abort(403, 'Zaproszenie zostało odwołane.');
        }

        if ($invitation->isExpired()) {
            $invitation->update([
                'status' => ShootingRangeInvitation::STATUS_EXPIRED,
            ]);

            abort(403, 'Zaproszenie wygasło.');
        }

        if ($invitation->isAccepted()) {
            return redirect()->route('index')
                ->with('success', 'Zaproszenie było już zaakceptowane.');
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

    private function refreshInvitationLink(ShootingRangeInvitation $invitation, ?string $expiresAt = null): bool
    {
        $selectedExpiry = $expiresAt ? Carbon::parse($expiresAt) : null;
        $renewExpiredDate = ! $selectedExpiry && $invitation->expires_at && $invitation->expires_at->isPast();

        $updates = [
            'token' => Str::random(64),
            'status' => ShootingRangeInvitation::STATUS_PENDING,
            'revoked_at' => null,
        ];

        if ($selectedExpiry) {
            $updates['expires_at'] = $selectedExpiry;
            $updates['expires_soon_notified_at'] = null;
        } elseif ($renewExpiredDate) {
            $updates['expires_at'] = now()->addDays(self::RENEWED_INVITATION_DAYS);
            $updates['expires_soon_notified_at'] = null;
        }

        $invitation->update($updates);
        $invitation->refresh();

        return (bool) $renewExpiredDate;
    }

    private function authorizeInvitation(ShootingRangeInvitation $invitation): void
    {
        $shootingRange = $this->userShootingRange();

        abort_if($invitation->shooting_range_id !== $shootingRange->id, 403);
    }
}

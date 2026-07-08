<?php

namespace App\Support;

use App\Models\ShootingRangeInvitation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class PendingInvitationSession
{
    public static function syncForUser(Request $request, User $user): void
    {
        $email = mb_strtolower((string) $user->email);
        $preferredToken = $request->session()->get('pending_invitation_token');

        $invitations = ShootingRangeInvitation::query()
            ->with('shootingRange')
            ->whereRaw('LOWER(email) = ?', [$email])
            ->where('status', ShootingRangeInvitation::STATUS_PENDING)
            ->whereNotNull('token')
            ->where(function ($query) {
                $query
                    ->whereNull('expires_at')
                    ->orWhere('expires_at', '>=', now());
            })
            ->latest('updated_at')
            ->get();

        if ($invitations->isEmpty()) {
            self::forget($request);

            return;
        }

        if ($preferredToken && $invitations->contains('token', $preferredToken)) {
            $invitations = self::putPreferredInvitationFirst($invitations, $preferredToken);
        }

        $serializedInvitations = $invitations
            ->map(fn (ShootingRangeInvitation $invitation) => self::serializeInvitation($invitation))
            ->values()
            ->all();
        $firstInvitation = $serializedInvitations[0];

        $request->session()->forget('pending_invitation_snoozed_until');

        $request->session()->put([
            'pending_invitations' => $serializedInvitations,
            'pending_invitation_token' => $firstInvitation['token'],
            'pending_invitation_email' => $firstInvitation['email'],
            'pending_invitation_range_name' => $firstInvitation['shooting_range_name'],
        ]);
    }

    public static function storeSingle(Request $request, ShootingRangeInvitation $invitation, bool $openModal = false): void
    {
        $serializedInvitation = self::serializeInvitation($invitation->loadMissing('shootingRange'));

        $request->session()->put([
            'pending_invitations' => [$serializedInvitation],
            'pending_invitation_token' => $serializedInvitation['token'],
            'pending_invitation_email' => $serializedInvitation['email'],
            'pending_invitation_range_name' => $serializedInvitation['shooting_range_name'],
        ]);

        if ($openModal) {
            $request->session()->put('pending_invitation_open_modal', true);
        }
    }

    public static function forget(Request $request): void
    {
        $request->session()->forget([
            'pending_invitations',
            'pending_invitation_token',
            'pending_invitation_email',
            'pending_invitation_range_name',
            'pending_invitation_snoozed_until',
            'pending_invitation_open_modal',
        ]);
    }

    private static function serializeInvitation(ShootingRangeInvitation $invitation): array
    {
        return [
            'token' => $invitation->token,
            'email' => mb_strtolower((string) $invitation->email),
            'shooting_range_name' => $invitation->shootingRange?->name
                ?? 'Czeka na Ciebie zaproszenie do prywatnej strzelnicy.',
            'expires_at' => $invitation->expires_at?->format('Y-m-d H:i'),
        ];
    }

    private static function putPreferredInvitationFirst(Collection $invitations, string $preferredToken): Collection
    {
        return $invitations
            ->sortByDesc(fn (ShootingRangeInvitation $invitation) => $invitation->token === $preferredToken ? 1 : 0)
            ->values();
    }
}

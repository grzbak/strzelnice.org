<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserSecurityEvent;
use App\Services\UserSecurityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class UserController extends Controller
{
    public function userActivate(
        Request $request,
        UserSecurityService $security,
        $id
    ) {
        $user = User::findOrFail($id);
        $attributes = [
            'is_approved' => true,
        ];

        if (Schema::hasColumn('users', 'approval_status')) {
            $attributes += [
                'approval_status' => User::APPROVAL_APPROVED,
                'approval_reason' => null,
                'approved_at' => now(),
                'approved_by_user_id' => $request->user()?->id,
            ];
        }

        $user->update($attributes);

        if (Schema::hasTable('user_security_events')) {
            UserSecurityEvent::create([
                'user_id' => $user->id,
                'ip_address' => $request->ip(),
                'event_type' => UserSecurityEvent::TYPE_ACCOUNT_APPROVED,
                'severity' => UserSecurityEvent::SEVERITY_INFO,
                'reason' => 'Konto zatwierdzone przez administratora.',
                'resolved_at' => now(),
                'resolved_by_user_id' => $request->user()?->id,
            ]);
        }

        $security->audit('user.approved', $request->user(), $request, $user);

        if (! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }

        return response()->json(['message' => 'Użytkownik aktywowany']);
    }

    public function userDeactivate(
        Request $request,
        UserSecurityService $security,
        $id
    ) {
        $user = User::findOrFail($id);
        $attributes = [
            'is_approved' => false,
        ];

        if (Schema::hasColumn('users', 'approval_status')) {
            $attributes += [
                'approval_status' => User::APPROVAL_PENDING,
                'approval_reason' => 'Konto oczekuje na ponowne zatwierdzenie przez administratora.',
                'approved_at' => null,
                'approved_by_user_id' => null,
            ];
        }

        $user->update($attributes);

        $security->audit('user.deactivated', $request->user(), $request, $user);

        return response()->json(['message' => 'Użytkownik dezaktywowany']);
    }
}

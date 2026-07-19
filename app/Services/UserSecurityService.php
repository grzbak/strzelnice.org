<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use App\Models\UserIpAddress;
use App\Models\UserSecurityEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class UserSecurityService
{
    public function recordIpAddress(User $user, ?Request $request, string $action): void
    {
        $ipAddress = $request?->ip();

        if (! $ipAddress || ! Schema::hasTable('user_ip_addresses')) {
            return;
        }

        $existing = UserIpAddress::query()
            ->where('user_id', $user->id)
            ->where('ip_address', $ipAddress)
            ->first();

        if ($existing) {
            $existing->update(['last_seen_at' => now()]);

            return;
        }

        UserIpAddress::create([
            'user_id' => $user->id,
            'ip_address' => $ipAddress,
            'first_action' => $action,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);
    }

    public function audit(
        string $action,
        ?User $user = null,
        ?Request $request = null,
        ?Model $model = null,
        array $payload = []
    ): void {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        AuditLog::create([
            'user_id' => $user?->id,
            'ip_address' => $request?->ip(),
            'action' => $action,
            'model_type' => $model ? $model::class : null,
            'model_id' => $model?->getKey(),
            'payload' => $payload ?: null,
            'user_agent' => $request?->userAgent(),
        ]);
    }

    public function requiresManualApproval(Request $request): bool
    {
        $ipAddress = $request->ip();

        if (! $ipAddress || ! Schema::hasTable('user_security_events')) {
            return false;
        }

        return UserSecurityEvent::query()
            ->active()
            ->where('ip_address', $ipAddress)
            ->where('event_type', UserSecurityEvent::TYPE_BAN)
            ->exists();
    }

    public function markSuspiciousRegistration(User $user, Request $request, string $reason): void
    {
        if (! Schema::hasTable('user_security_events')) {
            return;
        }

        UserSecurityEvent::create([
            'user_id' => $user->id,
            'ip_address' => $request->ip(),
            'event_type' => UserSecurityEvent::TYPE_SUSPICIOUS_REGISTRATION,
            'severity' => UserSecurityEvent::SEVERITY_WARNING,
            'reason' => $reason,
        ]);
    }

    public function recordBanForUser(User $user, ?Request $request, ?string $reason = null): void
    {
        if (! Schema::hasTable('user_security_events')) {
            return;
        }

        $ipAddresses = Schema::hasTable('user_ip_addresses')
            ? $user->ipAddresses()
                ->pluck('ip_address')
                ->filter()
                ->values()
            : collect();

        if ($request?->ip()) {
            $ipAddresses->push($request->ip());
        }

        $ipAddresses->unique()->each(function (string $ipAddress) use ($user, $reason): void {
            UserSecurityEvent::create([
                'user_id' => $user->id,
                'ip_address' => $ipAddress,
                'event_type' => UserSecurityEvent::TYPE_BAN,
                'severity' => UserSecurityEvent::SEVERITY_CRITICAL,
                'reason' => $reason,
            ]);
        });
    }
}

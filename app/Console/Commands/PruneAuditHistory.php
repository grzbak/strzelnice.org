<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\SystemConfiguration;
use App\Models\UserIpAddress;
use App\Models\UserSecurityEvent;
use Illuminate\Console\Command;

class PruneAuditHistory extends Command
{
    protected $signature = 'security:prune-audit-history';

    protected $description = 'Removes old IP, audit and inactive security history according to system configuration.';

    public function handle(): int
    {
        $configuration = SystemConfiguration::query()
            ->active()
            ->latest('id')
            ->first();

        $ipMonths = max(1, (int) ($configuration?->ip_retention_months ?? 24));
        $auditMonths = max(1, (int) ($configuration?->audit_retention_months ?? 24));
        $securityMonths = max(1, (int) ($configuration?->security_events_retention_months ?? 60));

        $deletedIpAddresses = UserIpAddress::query()
            ->where('last_seen_at', '<', now()->subMonths($ipMonths))
            ->delete();

        $deletedAuditLogs = AuditLog::query()
            ->where('created_at', '<', now()->subMonths($auditMonths))
            ->delete();

        $deletedSecurityEvents = UserSecurityEvent::query()
            ->where('created_at', '<', now()->subMonths($securityMonths))
            ->where(function ($query): void {
                $query->whereNotNull('resolved_at')
                    ->orWhere(function ($query): void {
                        $query->whereNotNull('expires_at')
                            ->where('expires_at', '<=', now());
                    });
            })
            ->delete();

        $this->info("Removed IP addresses: {$deletedIpAddresses}");
        $this->info("Removed audit logs: {$deletedAuditLogs}");
        $this->info("Removed inactive security events: {$deletedSecurityEvents}");

        return self::SUCCESS;
    }
}

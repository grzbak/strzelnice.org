<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_configurations', function (Blueprint $table) {
            $table->unsignedSmallInteger('ip_retention_months')->default(24)->after('reservation_history_months');
            $table->unsignedSmallInteger('audit_retention_months')->default(24)->after('ip_retention_months');
            $table->unsignedSmallInteger('security_events_retention_months')->default(60)->after('audit_retention_months');
        });
    }

    public function down(): void
    {
        Schema::table('system_configurations', function (Blueprint $table) {
            $table->dropColumn([
                'ip_retention_months',
                'audit_retention_months',
                'security_events_retention_months',
            ]);
        });
    }
};

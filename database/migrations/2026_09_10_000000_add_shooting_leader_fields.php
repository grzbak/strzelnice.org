<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shooting_ranges', function (Blueprint $table): void {
            if (! Schema::hasColumn('shooting_ranges', 'requires_shooting_leader')) {
                $table->boolean('requires_shooting_leader')
                    ->default(false)
                    ->after('requires_reservation_confirmation');
            }
        });

        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'has_shooting_leader_license')) {
                $table->boolean('has_shooting_leader_license')
                    ->default(false)
                    ->after('telephone');
            }

            if (! Schema::hasColumn('users', 'shooting_leader_license_number')) {
                $table->string('shooting_leader_license_number', 100)
                    ->nullable()
                    ->after('has_shooting_leader_license');
            }
        });

        Schema::table('reservations', function (Blueprint $table): void {
            if (! Schema::hasColumn('reservations', 'has_shooting_leader_license')) {
                $table->boolean('has_shooting_leader_license')
                    ->default(false)
                    ->after('is_dynamic_shooting');
            }

            if (! Schema::hasColumn('reservations', 'shooting_leader_license_number')) {
                $table->string('shooting_leader_license_number', 100)
                    ->nullable()
                    ->after('has_shooting_leader_license');
            }
        });

        Schema::table('reservation_archives', function (Blueprint $table): void {
            if (! Schema::hasColumn('reservation_archives', 'has_shooting_leader_license')) {
                $table->boolean('has_shooting_leader_license')
                    ->default(false)
                    ->after('is_dynamic_shooting');
            }

            if (! Schema::hasColumn('reservation_archives', 'shooting_leader_license_number')) {
                $table->string('shooting_leader_license_number', 100)
                    ->nullable()
                    ->after('has_shooting_leader_license');
            }
        });
    }

    public function down(): void
    {
        Schema::table('reservation_archives', function (Blueprint $table): void {
            if (Schema::hasColumn('reservation_archives', 'shooting_leader_license_number')) {
                $table->dropColumn('shooting_leader_license_number');
            }

            if (Schema::hasColumn('reservation_archives', 'has_shooting_leader_license')) {
                $table->dropColumn('has_shooting_leader_license');
            }
        });

        Schema::table('reservations', function (Blueprint $table): void {
            if (Schema::hasColumn('reservations', 'shooting_leader_license_number')) {
                $table->dropColumn('shooting_leader_license_number');
            }

            if (Schema::hasColumn('reservations', 'has_shooting_leader_license')) {
                $table->dropColumn('has_shooting_leader_license');
            }
        });

        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'shooting_leader_license_number')) {
                $table->dropColumn('shooting_leader_license_number');
            }

            if (Schema::hasColumn('users', 'has_shooting_leader_license')) {
                $table->dropColumn('has_shooting_leader_license');
            }
        });

        Schema::table('shooting_ranges', function (Blueprint $table): void {
            if (Schema::hasColumn('shooting_ranges', 'requires_shooting_leader')) {
                $table->dropColumn('requires_shooting_leader');
            }
        });
    }
};

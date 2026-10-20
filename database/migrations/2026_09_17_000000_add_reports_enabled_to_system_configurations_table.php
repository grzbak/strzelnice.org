<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_configurations', function (Blueprint $table) {
            if (! Schema::hasColumn('system_configurations', 'reports_enabled')) {
                $table->boolean('reports_enabled')
                    ->default(true)
                    ->after('google_analytics_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('system_configurations', function (Blueprint $table) {
            if (Schema::hasColumn('system_configurations', 'reports_enabled')) {
                $table->dropColumn('reports_enabled');
            }
        });
    }
};

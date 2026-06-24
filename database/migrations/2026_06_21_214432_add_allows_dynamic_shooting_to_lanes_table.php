<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lanes', function (Blueprint $table) {
            $table->boolean('allows_dynamic_shooting')
                ->default(false)
                ->after('numberOfPositions');
        });
    }

    public function down(): void
    {
        Schema::table('lanes', function (Blueprint $table) {
            $table->dropColumn('allows_dynamic_shooting');
        });
    }
};

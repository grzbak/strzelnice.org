<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shooting_ranges', function (Blueprint $table) {
            $table->boolean('is_public_access')
                ->default(true)
                ->after('is_visible');
        });
    }

    public function down(): void
    {
        Schema::table('shooting_ranges', function (Blueprint $table) {
            $table->dropColumn('is_public_access');
        });
    }
};

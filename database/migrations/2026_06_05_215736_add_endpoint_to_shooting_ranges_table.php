<?php

use App\Models\ShootingRange;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shooting_ranges', function (Blueprint $table) {
            $table->string('endpoint')->nullable()->after('name');
        });

        ShootingRange::query()
            ->whereNull('endpoint')
            ->orWhere('endpoint', '')
            ->get()
            ->each(function (ShootingRange $shootingRange) {
                $shootingRange->endpoint = ShootingRange::generateUniqueEndpoint(
                    $shootingRange->name,
                    $shootingRange->town,
                    $shootingRange->id
                );

                $shootingRange->saveQuietly();
            });

        Schema::table('shooting_ranges', function (Blueprint $table) {
            $table->string('endpoint')->nullable(false)->change();
            $table->unique('endpoint');
        });
    }

    public function down(): void
    {
        Schema::table('shooting_ranges', function (Blueprint $table) {
            $table->dropUnique(['endpoint']);
            $table->dropColumn('endpoint');
        });
    }
};

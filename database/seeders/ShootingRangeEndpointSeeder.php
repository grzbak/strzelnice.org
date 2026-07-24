<?php

namespace Database\Seeders;

use App\Models\ShootingRange;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ShootingRangeEndpointSeeder extends Seeder
{
    /**
     * Rebuilds public shooting range endpoints using the current model rules.
     */
    public function run(): void
    {
        DB::transaction(function () {
            $shootingRanges = ShootingRange::query()
                ->orderBy('id')
                ->get();

            foreach ($shootingRanges as $shootingRange) {
                $shootingRange->forceFill([
                    'endpoint' => 'temporary-endpoint-'.$shootingRange->id,
                ])->saveQuietly();
            }

            foreach ($shootingRanges as $shootingRange) {
                $shootingRange->refresh();

                $shootingRange->forceFill([
                    'endpoint' => ShootingRange::generateUniqueEndpoint(
                        $shootingRange->name,
                        $shootingRange->town,
                        $shootingRange->id
                    ),
                ])->saveQuietly();
            }
        });
    }
}

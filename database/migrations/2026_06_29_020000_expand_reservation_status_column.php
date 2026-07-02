<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                'ALTER TABLE reservations MODIFY status VARCHAR(30) NOT NULL'
            );
        }
    }

    public function down(): void
    {
        // The original enum cannot be restored without losing newer statuses.
    }
};

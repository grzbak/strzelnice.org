<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Tymczasowo dopuszczamy obie wartości.
        if (DB::getDriverName() === 'mysql') {
            DB::statement("
                ALTER TABLE shooting_ranges
                MODIFY type ENUM('otwarta', 'zamknieta', 'kryta')
                NOT NULL DEFAULT 'otwarta'
            ");
        }

        // Aktualizujemy istniejące strzelnice.
        DB::table('shooting_ranges')
            ->where('type', 'zamknieta')
            ->update([
                'type' => 'kryta',
            ]);

        // Usuwamy starą wartość z enum.
        if (DB::getDriverName() === 'mysql') {
            DB::statement("
                ALTER TABLE shooting_ranges
                MODIFY type ENUM('otwarta', 'kryta')
                NOT NULL DEFAULT 'otwarta'
            ");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("
                ALTER TABLE shooting_ranges
                MODIFY type ENUM('otwarta', 'zamknieta', 'kryta')
                NOT NULL DEFAULT 'otwarta'
            ");
        }

        DB::table('shooting_ranges')
            ->where('type', 'kryta')
            ->update([
                'type' => 'zamknieta',
            ]);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("
                ALTER TABLE shooting_ranges
                MODIFY type ENUM('otwarta', 'zamknieta')
                NOT NULL DEFAULT 'otwarta'
            ");
        }
    }
};

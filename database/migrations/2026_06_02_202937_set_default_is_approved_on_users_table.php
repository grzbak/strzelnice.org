<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE users MODIFY is_approved TINYINT(1) NOT NULL DEFAULT 1');

        DB::table('users')->update([
            'is_approved' => 1,
        ]);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE users MODIFY is_approved TINYINT(1) NOT NULL DEFAULT 0');

        DB::table('users')->update([
            'is_approved' => 0,
        ]);
    }
};

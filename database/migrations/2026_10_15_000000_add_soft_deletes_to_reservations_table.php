<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table): void {
            $table->softDeletes();
            $table->foreignId('deleted_by_user_id')
                ->nullable()
                ->after('deleted_at')
                ->constrained('users')
                ->nullOnDelete();
            $table->string('deletion_reason', 500)
                ->nullable()
                ->after('deleted_by_user_id');

            $table->index(
                ['shooting_range_id', 'deleted_at'],
                'reservations_range_deleted_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table): void {
            $table->dropIndex('reservations_range_deleted_idx');
            $table->dropConstrainedForeignId('deleted_by_user_id');
            $table->dropColumn('deletion_reason');
            $table->dropSoftDeletes();
        });
    }
};

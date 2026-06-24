<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shooting_range_interval_archive', function (Blueprint $table) {
            $table->foreignId('shooting_range_interval_id')
                ->nullable()
                ->after('id')
                ->constrained(
                    table: 'shooting_range_interval',
                    indexName: 'sria_interval_fk'
                )
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('shooting_range_interval_archive', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shooting_range_interval_id');
        });
    }
};

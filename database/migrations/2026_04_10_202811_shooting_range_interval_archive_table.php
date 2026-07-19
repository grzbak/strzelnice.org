<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('shooting_range_interval_archive', function (Blueprint $table) {
            $table->id();

            // FK do strzelnicy
            $table->foreignId('shooting_range_id')
                ->constrained('shooting_ranges')
                ->cascadeOnDelete();

            // data rozpoczęcia
            $table->date('start_date');

            // data zkończenia
            $table->date('end_date');

            // operator insert
            $table->foreignId('operator_insert_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // operator update
            $table->foreignId('operator_update_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // wartość
            $table->decimal('value', 10, 2)->default(0);

            // data zapłaty
            $table->dateTime('datePaid')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shooting_range_interval_archive');
    }
};

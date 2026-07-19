<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shooting_range_opening_hours', function (Blueprint $table) {
            $table->id();

            // FK do strzelnicy
            $table->foreignId('shooting_range_id')
                ->constrained('shooting_ranges')
                ->cascadeOnDelete();

            // dzień tygodnia: 1 = poniedziałek, 7 = niedziela
            $table->unsignedTinyInteger('day_of_week')
                ->comment('1=Mon, 2=Tue, ..., 7=Sun');

            // godziny
            $table->time('opens_at');
            $table->time('closes_at');

            $table->timestamps();

            // zabezpieczenie: jeden wpis na dzień
            // $table->unique(['shooting_range_id', 'day_of_week']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shooting_range_opening_hours');
    }
};

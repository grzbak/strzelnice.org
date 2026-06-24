<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shooting_range_holidays', function (Blueprint $table) {
            $table->id();

            $table->foreignId('shooting_range_id')
                ->constrained('shooting_ranges')
                ->cascadeOnDelete();

            $table->date('date');

            $table->string('name');

            $table->text('description')
                ->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shooting_range_holidays');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shooting_range_packages', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('shooting_range_id')
                ->default(0);

            $table->unsignedInteger('period_months');

            $table->decimal('value', 10, 2);

            $table->date('valid_from');

            $table->timestamps();

            $table->index('shooting_range_id');
            $table->index('valid_from');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shooting_range_packages');
    }
};

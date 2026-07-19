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
        Schema::create('shooting_ranges', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('voivodeship');
            $table->string('town');
            $table->string('street');
            $table->string('houseNumber');
            $table->string('apartamentNumber')->nullable();
            $table->string('plotNumber')->nullable();
            $table->string('zipCode');
            $table->text('navLink');
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->string('email');
            $table->string('url')->nullable();
            $table->string('facebook')->nullable();
            $table->string('instagram')->nullable();
            $table->string('telephone1');
            $table->string('telephone2')->nullable();
            $table->enum('type', ['otwarta', 'zamknieta'])->default('otwarta');
            $table->string('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shooting_ranges');
    }
};

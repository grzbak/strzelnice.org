<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shooting_ranges', function (Blueprint $table) {
            $table->string('street')->nullable()->change();
            $table->string('houseNumber')->nullable()->change();
            $table->string('zipCode')->nullable()->change();
            $table->string('email')->nullable()->change();
            $table->string('telephone1')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('shooting_ranges', function (Blueprint $table) {
            $table->string('street')->nullable(false)->change();
            $table->string('houseNumber')->nullable(false)->change();
            $table->string('zipCode')->nullable(false)->change();
            $table->string('email')->nullable(false)->change();
            $table->string('telephone1')->nullable(false)->change();
        });
    }
};

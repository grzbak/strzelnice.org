<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('company_name_1')->nullable();
            $table->string('company_name_2')->nullable();
            $table->string('company_name_3')->nullable();
            $table->string('street')->nullable();
            $table->string('building_number', 30)->nullable();
            $table->string('unit_number', 30)->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->string('city')->nullable();
            $table->string('voivodeship', 100)->nullable();
            $table->string('phone_1', 50)->nullable();
            $table->string('phone_2', 50)->nullable();
            $table->string('fax', 50)->nullable();
            $table->string('email')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bank_account_number', 50)->nullable();
            $table->boolean('is_archived')->default(false)->index();
            $table->timestamps();
        });

        DB::table('system_configurations')->insert([
            'is_archived' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('system_configurations');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shooting_range_payment_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shooting_range_id')->unique()->constrained('shooting_ranges')->cascadeOnDelete();
            $table->boolean('p24_enabled')->default(false)->index();
            $table->boolean('p24_sandbox')->default(true);
            $table->string('p24_merchant_id', 50)->nullable();
            $table->string('p24_pos_id', 50)->nullable();
            $table->string('p24_crc')->nullable();
            $table->string('p24_api_key')->nullable();
            $table->string('currency', 3)->default('PLN');
            $table->string('status', 40)->default('not_configured')->index();
            $table->dateTime('last_tested_at')->nullable();
            $table->boolean('last_test_success')->nullable();
            $table->text('last_test_message')->nullable();
            $table->timestamps();
        });

        Schema::create('reservation_price_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shooting_range_id')->constrained('shooting_ranges')->cascadeOnDelete();
            $table->foreignId('lane_id')->nullable()->constrained('lanes')->nullOnDelete();
            $table->string('name');
            $table->json('weekdays')->nullable();
            $table->time('time_from')->nullable();
            $table->time('time_to')->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->decimal('price_per_position_30min', 10, 2);
            $table->decimal('dynamic_price_per_position_30min', 10, 2)->nullable();
            $table->string('currency', 3)->default('PLN');
            $table->unsignedSmallInteger('priority')->default(100);
            $table->boolean('is_active')->default(true)->index();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['shooting_range_id', 'is_active'], 'rrules_range_active_idx');
            $table->index(['shooting_range_id', 'lane_id'], 'rrules_range_lane_idx');
        });

        Schema::create('reservation_price_promotions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shooting_range_id')->constrained('shooting_ranges')->cascadeOnDelete();
            $table->foreignId('lane_id')->nullable()->constrained('lanes')->nullOnDelete();
            $table->string('name');
            $table->string('discount_type', 20);
            $table->decimal('discount_value', 10, 2);
            $table->json('weekdays')->nullable();
            $table->time('time_from')->nullable();
            $table->time('time_to')->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->unsignedSmallInteger('priority')->default(100);
            $table->boolean('is_active')->default(true)->index();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['shooting_range_id', 'is_active'], 'rpromos_range_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_price_promotions');
        Schema::dropIfExists('reservation_price_rules');
        Schema::dropIfExists('shooting_range_payment_accounts');
    }
};

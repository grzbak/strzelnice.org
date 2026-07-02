<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservation_archives', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('original_reservation_id')->unique();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('mod_id')->nullable();
            $table->unsignedBigInteger('customer_user_id')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('customer_phone', 50)->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->string('source', 30)->nullable();
            $table->unsignedBigInteger('shooting_range_id')->nullable();
            $table->string('shooting_range_name')->nullable();
            $table->string('shooting_range_town')->nullable();
            $table->unsignedBigInteger('line_id')->nullable();
            $table->string('lane_name')->nullable();
            $table->unsignedInteger('lane_length')->nullable();
            $table->dateTime('start');
            $table->dateTime('end');
            $table->unsignedSmallInteger('positions_count')->default(1);
            $table->boolean('is_dynamic_shooting')->default(false);
            $table->string('status', 30);
            $table->unsignedBigInteger('approved_by_user_id')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('cancelled_by_user_id')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->unsignedBigInteger('finalized_by_user_id')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->string('cancellation_reason', 500)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('reservation_created_at')->nullable();
            $table->timestamp('reservation_updated_at')->nullable();
            $table->dateTime('archived_at');

            $table->index(
                ['customer_user_id', 'end'],
                'reservation_archives_customer_end_index'
            );
            $table->index(
                ['shooting_range_id', 'end'],
                'reservation_archives_range_end_index'
            );
            $table->index(
                ['status', 'end'],
                'reservation_archives_status_end_index'
            );
            $table->index('end');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_archives');
    }
};

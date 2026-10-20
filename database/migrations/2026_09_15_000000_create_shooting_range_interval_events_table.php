<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('shooting_range_interval_events');

        Schema::create('shooting_range_interval_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shooting_range_interval_id')->nullable();
            $table->unsignedBigInteger('shooting_range_id');
            $table->unsignedBigInteger('package_order_id')->nullable();
            $table->unsignedBigInteger('payment_id')->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->string('type', 60);
            $table->string('source', 40)->default('manual');
            $table->date('date_from')->nullable();
            $table->date('date_to')->nullable();
            $table->date('previous_start_date')->nullable();
            $table->date('previous_end_date')->nullable();
            $table->date('new_start_date')->nullable();
            $table->date('new_end_date')->nullable();
            $table->decimal('amount', 10, 2)->nullable();
            $table->string('currency', 3)->nullable();
            $table->string('payment_status', 40)->nullable();
            $table->string('payment_method', 40)->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index('type', 'srie_type_idx');
            $table->index('source', 'srie_source_idx');
            $table->index(['shooting_range_id', 'created_at'], 'srie_range_created_idx');
            $table->index(['shooting_range_interval_id', 'created_at'], 'srie_interval_created_idx');

            $table->foreign('shooting_range_interval_id', 'srie_interval_fk')
                ->references('id')
                ->on('shooting_range_interval')
                ->nullOnDelete();
            $table->foreign('shooting_range_id', 'srie_range_fk')
                ->references('id')
                ->on('shooting_ranges')
                ->cascadeOnDelete();
            $table->foreign('package_order_id', 'srie_order_fk')
                ->references('id')
                ->on('package_orders')
                ->nullOnDelete();
            $table->foreign('payment_id', 'srie_payment_fk')
                ->references('id')
                ->on('payments')
                ->nullOnDelete();
            $table->foreign('created_by_user_id', 'srie_user_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });

        DB::table('package_orders')
            ->whereNotNull('shooting_range_interval_id')
            ->orderBy('id')
            ->get()
            ->each(function ($order): void {
                DB::table('shooting_range_interval_events')->insert([
                    'shooting_range_interval_id' => $order->shooting_range_interval_id,
                    'shooting_range_id' => $order->shooting_range_id,
                    'package_order_id' => $order->id,
                    'created_by_user_id' => $order->created_by_user_id,
                    'type' => 'order_created',
                    'source' => 'package_order',
                    'date_from' => $order->starts_at,
                    'date_to' => $order->ends_at,
                    'new_start_date' => $order->starts_at,
                    'new_end_date' => $order->ends_at,
                    'amount' => $order->amount_gross,
                    'currency' => $order->currency,
                    'payment_status' => $order->payment_status,
                    'payment_method' => $order->payment_method,
                    'note' => $order->manual_reason ?: $order->notes,
                    'created_at' => $order->created_at,
                    'updated_at' => $order->updated_at,
                ]);
            });

        DB::table('shooting_range_interval')
            ->whereNull('package_order_id')
            ->orderBy('id')
            ->get()
            ->each(function ($interval): void {
                DB::table('shooting_range_interval_events')->insert([
                    'shooting_range_interval_id' => $interval->id,
                    'shooting_range_id' => $interval->shooting_range_id,
                    'created_by_user_id' => $interval->operator_insert_id,
                    'type' => 'manual_extended',
                    'source' => 'manual',
                    'date_from' => $interval->start_date,
                    'date_to' => $interval->end_date,
                    'new_start_date' => $interval->start_date,
                    'new_end_date' => $interval->end_date,
                    'amount' => $interval->value,
                    'created_at' => $interval->created_at,
                    'updated_at' => $interval->updated_at,
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('shooting_range_interval_events');
    }
};

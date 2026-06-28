<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->foreignId('customer_user_id')->nullable()->after('id');
            $table->string('customer_name')->nullable()->after('customer_user_id');
            $table->string('customer_email')->nullable()->after('customer_name');
            $table->string('customer_phone', 50)->nullable()->after('customer_email');
            $table->foreignId('created_by_user_id')->nullable()->after('customer_phone');
            $table->string('source', 30)->default('online_account')->after('created_by_user_id');
            $table->foreignId('approved_by_user_id')->nullable()->after('status');
            $table->timestamp('approved_at')->nullable()->after('approved_by_user_id');
            $table->foreignId('cancelled_by_user_id')->nullable()->after('approved_at');
            $table->timestamp('cancelled_at')->nullable()->after('cancelled_by_user_id');
            $table->string('cancellation_reason', 500)->nullable()->after('cancelled_at');
            $table->text('notes')->nullable()->after('cancellation_reason');

            $table->index(
                ['shooting_range_id', 'start', 'end', 'status'],
                'reservations_range_period_status_index'
            );
            $table->index(
                ['line_id', 'start', 'end', 'status'],
                'reservations_lane_period_status_index'
            );
            $table->index('customer_user_id');
            $table->index('customer_email');
        });

        DB::table('reservations')
            ->orderBy('id')
            ->each(function (object $reservation) {
                $user = DB::table('users')->find($reservation->user_id);

                DB::table('reservations')
                    ->where('id', $reservation->id)
                    ->update([
                        'customer_user_id' => $reservation->user_id,
                        'customer_name' => $user->name ?? 'Brak danych',
                        'customer_email' => $user->email ?? null,
                        'customer_phone' => $user->telephone ?? null,
                        'created_by_user_id' => $reservation->mod_id,
                        'source' => 'online_account',
                    ]);
            });

        Schema::table('reservations', function (Blueprint $table) {
            $table->foreign('customer_user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
            $table->foreign('created_by_user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
            $table->foreign('approved_by_user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
            $table->foreign('cancelled_by_user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropForeign(['customer_user_id']);
            $table->dropForeign(['created_by_user_id']);
            $table->dropForeign(['approved_by_user_id']);
            $table->dropForeign(['cancelled_by_user_id']);
            $table->dropIndex('reservations_range_period_status_index');
            $table->dropIndex('reservations_lane_period_status_index');
            $table->dropIndex(['customer_user_id']);
            $table->dropIndex(['customer_email']);
            $table->dropColumn([
                'customer_user_id',
                'customer_name',
                'customer_email',
                'customer_phone',
                'created_by_user_id',
                'source',
                'approved_by_user_id',
                'approved_at',
                'cancelled_by_user_id',
                'cancelled_at',
                'cancellation_reason',
                'notes',
            ]);
        });
    }
};

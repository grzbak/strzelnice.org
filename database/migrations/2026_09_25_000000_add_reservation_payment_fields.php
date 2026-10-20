<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->boolean('payment_required')->default(false)->after('status');
            $table->string('payment_status', 40)->default('not_required')->after('payment_required')->index();
            $table->decimal('payment_amount', 10, 2)->nullable()->after('payment_status');
            $table->string('payment_currency', 3)->default('PLN')->after('payment_amount');
            $table->dateTime('payment_expires_at')->nullable()->after('payment_currency')->index();
            $table->dateTime('paid_at')->nullable()->after('payment_expires_at');
            $table->string('payment_method', 40)->nullable()->after('paid_at');
        });

        Schema::table('shooting_range_payment_accounts', function (Blueprint $table) {
            $table->unsignedSmallInteger('reservation_payment_time_limit_minutes')
                ->default(15)
                ->after('currency');
        });
    }

    public function down(): void
    {
        Schema::table('shooting_range_payment_accounts', function (Blueprint $table) {
            $table->dropColumn('reservation_payment_time_limit_minutes');
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn([
                'payment_required',
                'payment_status',
                'payment_amount',
                'payment_currency',
                'payment_expires_at',
                'paid_at',
                'payment_method',
            ]);
        });
    }
};

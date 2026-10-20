<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['shooting_range_interval', 'shooting_range_interval_archive'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignId('package_order_id')->nullable()->after('shooting_range_id')
                    ->constrained('package_orders')->nullOnDelete();
                $table->foreignId('payment_id')->nullable()->after('package_order_id')
                    ->constrained('payments')->nullOnDelete();
                $table->string('source', 40)->default('manual')->after('payment_id');
                $table->string('subscription_status', 40)->default('active')->after('source')->index();
                $table->foreignId('activated_by_user_id')->nullable()->after('subscription_status')
                    ->constrained('users')->nullOnDelete();
                $table->dateTime('activated_at')->nullable()->after('activated_by_user_id');
                $table->dateTime('blocked_at')->nullable()->after('activated_at');
            });
        }

        Schema::table('system_configurations', function (Blueprint $table) {
            $table->boolean('p24_enabled')->default(false)->after('google_analytics_id');
            $table->boolean('p24_sandbox')->default(true)->after('p24_enabled');
            $table->string('p24_merchant_id')->nullable()->after('p24_sandbox');
            $table->string('p24_pos_id')->nullable()->after('p24_merchant_id');
            $table->string('p24_crc')->nullable()->after('p24_pos_id');
            $table->string('p24_api_key')->nullable()->after('p24_crc');
            $table->string('p24_currency', 3)->default('PLN')->after('p24_api_key');
            $table->unsignedSmallInteger('p24_time_limit')->nullable()->after('p24_currency');
            $table->unsignedSmallInteger('subscription_payment_due_days')->default(7)->after('p24_time_limit');
            $table->unsignedSmallInteger('conditional_access_charge_due_days')->default(7)->after('subscription_payment_due_days');
            $table->string('conditional_access_charge_mode', 40)->default('proportional')->after('conditional_access_charge_due_days');
        });
    }

    public function down(): void
    {
        Schema::table('system_configurations', function (Blueprint $table) {
            $table->dropColumn([
                'p24_enabled',
                'p24_sandbox',
                'p24_merchant_id',
                'p24_pos_id',
                'p24_crc',
                'p24_api_key',
                'p24_currency',
                'p24_time_limit',
                'subscription_payment_due_days',
                'conditional_access_charge_due_days',
                'conditional_access_charge_mode',
            ]);
        });

        foreach (['shooting_range_interval_archive', 'shooting_range_interval'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropConstrainedForeignId('package_order_id');
                $table->dropConstrainedForeignId('payment_id');
                $table->dropConstrainedForeignId('activated_by_user_id');
                $table->dropColumn([
                    'source',
                    'subscription_status',
                    'activated_at',
                    'blocked_at',
                ]);
            });
        }
    }
};

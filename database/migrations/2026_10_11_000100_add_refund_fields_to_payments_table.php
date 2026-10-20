<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->unsignedBigInteger('refunded_amount_minor')->default(0)->after('amount_minor');
            $table->dateTime('refunded_at')->nullable()->after('failed_at');
            $table->string('refund_request_id', 45)->nullable()->after('refunded_at')->index();
            $table->string('refunds_uuid', 35)->nullable()->after('refund_request_id')->index();
            $table->text('refund_error')->nullable()->after('refunds_uuid');
            $table->json('raw_refund_response')->nullable()->after('raw_register_response');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn([
                'refunded_amount_minor',
                'refunded_at',
                'refund_request_id',
                'refunds_uuid',
                'refund_error',
                'raw_refund_response',
            ]);
        });
    }
};

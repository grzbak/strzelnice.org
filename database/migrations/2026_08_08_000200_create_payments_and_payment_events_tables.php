<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->nullableMorphs('payable');
            $table->foreignId('package_order_id')->nullable()->constrained('package_orders')->cascadeOnDelete();
            $table->string('provider', 40)->default('przelewy24')->index();
            $table->string('method', 40)->default('p24')->index();
            $table->string('status', 40)->default('created')->index();
            $table->decimal('amount', 10, 2);
            $table->unsignedBigInteger('amount_minor');
            $table->string('currency', 3)->default('PLN');
            $table->string('description');
            $table->string('payer_email')->nullable();
            $table->string('payer_name')->nullable();
            $table->string('p24_session_id')->nullable()->unique();
            $table->string('p24_token')->nullable()->index();
            $table->unsignedBigInteger('p24_order_id')->nullable()->index();
            $table->unsignedInteger('p24_method')->nullable();
            $table->string('url_return')->nullable();
            $table->string('url_status')->nullable();
            $table->dateTime('registered_at')->nullable();
            $table->dateTime('verified_at')->nullable();
            $table->dateTime('failed_at')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->foreignId('confirmed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('confirmed_at')->nullable();
            $table->text('confirmation_note')->nullable();
            $table->text('note')->nullable();
            $table->json('raw_register_response')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->string('provider', 40)->default('przelewy24')->index();
            $table->string('event_type', 80)->index();
            $table->json('payload')->nullable();
            $table->boolean('signature_valid')->nullable();
            $table->dateTime('received_at')->nullable();
            $table->dateTime('processed_at')->nullable();
            $table->string('processing_status', 40)->default('received')->index();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_events');
        Schema::dropIfExists('payments');
    }
};

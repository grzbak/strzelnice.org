<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('package_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shooting_range_id')->constrained('shooting_ranges')->cascadeOnDelete();
            $table->foreignId('shooting_range_package_id')->nullable()->constrained('shooting_range_packages')->nullOnDelete();
            $table->foreignId('ordered_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('activated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('shooting_range_interval_id')->nullable()
                ->constrained('shooting_range_interval')->nullOnDelete();
            $table->string('status', 40)->default('conditional_active')->index();
            $table->string('payment_method', 40)->default('p24')->index();
            $table->string('payment_status', 40)->default('pending')->index();
            $table->decimal('amount_gross', 10, 2);
            $table->string('currency', 3)->default('PLN');
            $table->unsignedSmallInteger('period_months');
            $table->unsignedSmallInteger('bonus_months')->default(0);
            $table->date('starts_at');
            $table->date('ends_at');
            $table->dateTime('payment_due_at')->nullable()->index();
            $table->dateTime('paid_at')->nullable();
            $table->dateTime('activated_at')->nullable();
            $table->dateTime('expired_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->text('notes')->nullable();
            $table->text('internal_note')->nullable();
            $table->text('payment_note')->nullable();
            $table->text('manual_reason')->nullable();
            $table->timestamps();

            $table->index(['shooting_range_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_orders');
    }
};

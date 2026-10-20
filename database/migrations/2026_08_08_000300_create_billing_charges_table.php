<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shooting_range_id')->constrained('shooting_ranges')->cascadeOnDelete();
            $table->foreignId('package_order_id')->nullable()->constrained('package_orders')->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->string('type', 60)->default('conditional_access_fee')->index();
            $table->string('status', 40)->default('unpaid')->index();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('PLN');
            $table->date('period_from')->nullable();
            $table->date('period_to')->nullable();
            $table->dateTime('due_at')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->string('description')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('settled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['shooting_range_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_charges');
    }
};

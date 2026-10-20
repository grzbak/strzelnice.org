<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservation_change_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained('reservations')->cascadeOnDelete();
            $table->foreignId('requested_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('decided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 30)->index();
            $table->string('status', 30)->default('pending')->index();
            $table->foreignId('old_lane_id')->nullable()->constrained('lanes')->nullOnDelete();
            $table->foreignId('new_lane_id')->nullable()->constrained('lanes')->nullOnDelete();
            $table->dateTime('old_start')->nullable();
            $table->dateTime('old_end')->nullable();
            $table->dateTime('new_start')->nullable();
            $table->dateTime('new_end')->nullable();
            $table->unsignedInteger('old_positions_count')->nullable();
            $table->unsignedInteger('new_positions_count')->nullable();
            $table->boolean('old_is_dynamic_shooting')->nullable();
            $table->boolean('new_is_dynamic_shooting')->nullable();
            $table->boolean('old_has_shooting_leader_license')->nullable();
            $table->boolean('new_has_shooting_leader_license')->nullable();
            $table->string('old_shooting_leader_license_number', 100)->nullable();
            $table->string('new_shooting_leader_license_number', 100)->nullable();
            $table->text('customer_note')->nullable();
            $table->text('decision_note')->nullable();
            $table->dateTime('decided_at')->nullable();
            $table->timestamps();

            $table->index(['reservation_id', 'status'], 'rcr_res_status_idx');
            $table->index(['type', 'status'], 'rcr_type_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_change_requests');
    }
};

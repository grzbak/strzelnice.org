<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shooting_range_invitations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('shooting_range_id')
                ->constrained('shooting_ranges')
                ->cascadeOnDelete();

            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email');

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('token', 64)->nullable()->unique();

            $table->enum('status', [
                'none',
                'pending',
                'accepted',
                'revoked',
                'expired',
            ])->default('none');

            $table->dateTime('expires_at')->nullable();
            $table->dateTime('accepted_at')->nullable();
            $table->dateTime('revoked_at')->nullable();

            $table->foreignId('invited_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->unique(['shooting_range_id', 'email']);
            $table->index(['shooting_range_id', 'status']);
            $table->index(['email', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shooting_range_invitations');
    }
};

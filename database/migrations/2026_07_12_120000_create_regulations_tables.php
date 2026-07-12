<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regulations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->json('target_roles');
            $table->date('starts_at');
            $table->date('ends_at')->nullable();
            $table->boolean('is_indefinite')->default(false);
            $table->string('status')->default('active');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('regulation_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('regulation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('page_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('display_name');
            $table->longText('content')->nullable();
            $table->string('content_hash', 64);
            $table->timestamp('published_at');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['regulation_id', 'version_number']);
        });

        Schema::create('regulation_acceptances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('regulation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('regulation_version_id')->constrained()->cascadeOnDelete();
            $table->timestamp('accepted_at');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'regulation_version_id']);
            $table->index(['user_id', 'regulation_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regulation_acceptances');
        Schema::dropIfExists('regulation_versions');
        Schema::dropIfExists('regulations');
    }
};

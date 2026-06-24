<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shooting_range_gallery_categories', function (Blueprint $table) {
            $table->id();

            $table->foreignId('shooting_range_id')
                ->unique()
                ->constrained('shooting_ranges')
                ->cascadeOnDelete();

            $table->string('name');
            $table->string('folder_path')->unique();

            $table->unsignedBigInteger('capacity_bytes')
                ->default(50 * 1024 * 1024);

            $table->boolean('is_visible')->default(true);
            $table->boolean('is_deletable')->default(false);
            $table->boolean('allow_upload')->default(true);
            $table->boolean('allow_delete')->default(true);

            $table->timestamps();
        });

        Schema::create('shooting_range_gallery_photos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('category_id')
                ->constrained('shooting_range_gallery_categories')
                ->cascadeOnDelete();

            $table->string('stored_name')->unique();
            $table->string('original_name');
            $table->text('full_url');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('size_bytes');
            $table->string('format', 20);
            $table->timestamps();

            $table->index(['category_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shooting_range_gallery_photos');
        Schema::dropIfExists('shooting_range_gallery_categories');
    }
};

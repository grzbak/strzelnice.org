<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shooting_ranges', function (Blueprint $table) {
            $table->string('brand_original_name')->nullable()->after('description');
            $table->string('brand_file_name')->nullable()->after('brand_original_name');
        });
    }

    public function down(): void
    {
        Schema::table('shooting_ranges', function (Blueprint $table) {
            $table->dropColumn(['brand_original_name', 'brand_file_name']);
        });
    }
};

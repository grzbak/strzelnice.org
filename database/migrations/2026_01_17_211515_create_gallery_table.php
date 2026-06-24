<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('gallery', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('folder_path'); // ścieżka folderu np. 'public/gallery/natura'
            $table->integer('custom_no'); // kolejność wyświetlania
            $table->boolean('is_visible')->default(false); // czy jest widoczny w galerii
            $table->boolean('variable')->default(true); // czy kategoria jest zmienna(możliwość usunięcia/edycji)
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gallery');
    }
};

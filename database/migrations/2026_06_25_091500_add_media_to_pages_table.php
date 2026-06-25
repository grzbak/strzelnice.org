<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            if (! Schema::hasColumn('pages', 'baner')) {
                $table->string('baner')->nullable()->after('content');
            }

            if (! Schema::hasColumn('pages', 'background')) {
                $table->string('background')->nullable()->after('baner');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            if (Schema::hasColumn('pages', 'background')) {
                $table->dropColumn('background');
            }

            if (Schema::hasColumn('pages', 'baner')) {
                $table->dropColumn('baner');
            }
        });
    }
};
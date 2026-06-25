<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shooting_range_invitations', function (Blueprint $table) {
            $table->dateTime('expires_soon_notified_at')->nullable()->after('revoked_at');
        });
    }

    public function down(): void
    {
        Schema::table('shooting_range_invitations', function (Blueprint $table) {
            $table->dropColumn('expires_soon_notified_at');
        });
    }
};

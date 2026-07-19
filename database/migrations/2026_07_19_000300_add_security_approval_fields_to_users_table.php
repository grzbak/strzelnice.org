<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('approval_status', 30)->default('approved')->after('is_approved')->index();
            $table->text('approval_reason')->nullable()->after('approval_status');
            $table->timestamp('approved_at')->nullable()->after('approval_reason');
            $table->foreignId('approved_by_user_id')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
        });

        DB::table('users')->update([
            'approval_status' => DB::raw("CASE WHEN is_approved = 1 THEN 'approved' ELSE 'pending' END"),
        ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by_user_id');
            $table->dropColumn(['approval_status', 'approval_reason', 'approved_at']);
        });
    }
};

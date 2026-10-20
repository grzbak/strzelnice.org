<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shooting_range_packages', function (Blueprint $table) {
            $table->string('name')->nullable()->after('shooting_range_id');
            $table->text('description')->nullable()->after('name');
            $table->string('type', 40)->default('standard')->after('description');
            $table->decimal('price_gross', 10, 2)->nullable()->after('value');
            $table->decimal('vat_rate', 5, 2)->default(23)->after('price_gross');
            $table->string('currency', 3)->default('PLN')->after('vat_rate');
            $table->unsignedSmallInteger('bonus_months')->default(0)->after('period_months');
            $table->date('valid_to')->nullable()->after('valid_from');
            $table->date('available_from')->nullable()->after('valid_to');
            $table->date('available_to')->nullable()->after('available_from');
            $table->unsignedInteger('max_uses_total')->nullable()->after('available_to');
            $table->unsignedInteger('max_uses_per_shooting_range')->nullable()->after('max_uses_total');
            $table->boolean('is_active')->default(true)->index()->after('max_uses_per_shooting_range');
            $table->boolean('is_public')->default(true)->index()->after('is_active');
            $table->boolean('requires_superadmin_approval')->default(false)->index()->after('is_public');
            $table->foreignId('created_by_user_id')->nullable()->after('requires_superadmin_approval')
                ->constrained('users')->nullOnDelete();
        });

        DB::table('shooting_range_packages')->update([
            'name' => DB::raw("CONCAT(period_months, ' mies.')"),
            'price_gross' => DB::raw('value'),
        ]);
    }

    public function down(): void
    {
        Schema::table('shooting_range_packages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by_user_id');
            $table->dropColumn([
                'name',
                'description',
                'type',
                'price_gross',
                'vat_rate',
                'currency',
                'bonus_months',
                'valid_to',
                'available_from',
                'available_to',
                'max_uses_total',
                'max_uses_per_shooting_range',
                'is_active',
                'is_public',
                'requires_superadmin_approval',
            ]);
        });
    }
};

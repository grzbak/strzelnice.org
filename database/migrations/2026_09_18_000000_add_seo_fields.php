<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_configurations', function (Blueprint $table): void {
            if (! Schema::hasColumn('system_configurations', 'default_meta_title')) {
                $table->string('default_meta_title')->nullable()->after('reports_enabled');
            }

            if (! Schema::hasColumn('system_configurations', 'default_meta_description')) {
                $table->text('default_meta_description')->nullable()->after('default_meta_title');
            }

            if (! Schema::hasColumn('system_configurations', 'default_meta_keywords')) {
                $table->text('default_meta_keywords')->nullable()->after('default_meta_description');
            }

            if (! Schema::hasColumn('system_configurations', 'default_og_title')) {
                $table->string('default_og_title')->nullable()->after('default_meta_keywords');
            }

            if (! Schema::hasColumn('system_configurations', 'default_og_description')) {
                $table->text('default_og_description')->nullable()->after('default_og_title');
            }

            if (! Schema::hasColumn('system_configurations', 'default_og_image')) {
                $table->string('default_og_image')->nullable()->after('default_og_description');
            }

            if (! Schema::hasColumn('system_configurations', 'default_robots')) {
                $table->string('default_robots', 50)->nullable()->default('index,follow')->after('default_og_image');
            }
        });

        Schema::table('pages', function (Blueprint $table): void {
            if (! Schema::hasColumn('pages', 'meta_title')) {
                $table->string('meta_title')->nullable()->after('background');
            }

            if (! Schema::hasColumn('pages', 'meta_description')) {
                $table->text('meta_description')->nullable()->after('meta_title');
            }

            if (! Schema::hasColumn('pages', 'meta_keywords')) {
                $table->text('meta_keywords')->nullable()->after('meta_description');
            }

            if (! Schema::hasColumn('pages', 'og_title')) {
                $table->string('og_title')->nullable()->after('meta_keywords');
            }

            if (! Schema::hasColumn('pages', 'og_description')) {
                $table->text('og_description')->nullable()->after('og_title');
            }

            if (! Schema::hasColumn('pages', 'og_image')) {
                $table->string('og_image')->nullable()->after('og_description');
            }

            if (! Schema::hasColumn('pages', 'canonical_url')) {
                $table->string('canonical_url')->nullable()->after('og_image');
            }

            if (! Schema::hasColumn('pages', 'robots')) {
                $table->string('robots', 50)->nullable()->default('index,follow')->after('canonical_url');
            }
        });

        Schema::table('shooting_ranges', function (Blueprint $table): void {
            if (! Schema::hasColumn('shooting_ranges', 'meta_title')) {
                $table->string('meta_title')->nullable()->after('endpoint');
            }

            if (! Schema::hasColumn('shooting_ranges', 'meta_description')) {
                $table->text('meta_description')->nullable()->after('meta_title');
            }

            if (! Schema::hasColumn('shooting_ranges', 'meta_keywords')) {
                $table->text('meta_keywords')->nullable()->after('meta_description');
            }

            if (! Schema::hasColumn('shooting_ranges', 'og_title')) {
                $table->string('og_title')->nullable()->after('meta_keywords');
            }

            if (! Schema::hasColumn('shooting_ranges', 'og_description')) {
                $table->text('og_description')->nullable()->after('og_title');
            }

            if (! Schema::hasColumn('shooting_ranges', 'og_image')) {
                $table->string('og_image')->nullable()->after('og_description');
            }

            if (! Schema::hasColumn('shooting_ranges', 'robots')) {
                $table->string('robots', 50)->nullable()->default('index,follow')->after('og_image');
            }
        });
    }

    public function down(): void
    {
        Schema::table('shooting_ranges', function (Blueprint $table): void {
            foreach (['meta_title', 'meta_description', 'meta_keywords', 'og_title', 'og_description', 'og_image', 'robots'] as $column) {
                if (Schema::hasColumn('shooting_ranges', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('pages', function (Blueprint $table): void {
            foreach (['meta_title', 'meta_description', 'meta_keywords', 'og_title', 'og_description', 'og_image', 'canonical_url', 'robots'] as $column) {
                if (Schema::hasColumn('pages', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('system_configurations', function (Blueprint $table): void {
            foreach (['default_meta_title', 'default_meta_description', 'default_meta_keywords', 'default_og_title', 'default_og_description', 'default_og_image', 'default_robots'] as $column) {
                if (Schema::hasColumn('system_configurations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('statistics', function (Blueprint $table) {
            // ISO 3166-1 alpha-2, resolved by GeoIpService on every click.
            // Nullable: historical rows and rows whose name has no known code stay null.
            $table->char('country_code', 2)->nullable()->after('country');
            $table->index(['project_id', 'url_id', 'country_code'], 'statistics_project_url_country_code_index');
        });
    }

    public function down(): void
    {
        Schema::table('statistics', function (Blueprint $table) {
            $table->dropIndex('statistics_project_url_country_code_index');
            $table->dropColumn('country_code');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('statistics', function (Blueprint $table) {
            // Uniqueness check on every click: WHERE url_id = ? AND ip = ? AND created_at >= ?
            $table->index(['url_id', 'ip', 'created_at'], 'statistics_url_ip_created_index');
            // Dashboard aggregations are always scoped to a project + time range
            $table->index(['project_id', 'created_at'], 'statistics_project_created_index');
        });

        Schema::table('domains', function (Blueprint $table) {
            // Every redirect resolves the host: WHERE name = ?
            $table->index('name', 'domains_name_index');
        });
    }

    public function down(): void
    {
        Schema::table('statistics', function (Blueprint $table) {
            $table->dropIndex('statistics_url_ip_created_index');
            $table->dropIndex('statistics_project_created_index');
        });

        Schema::table('domains', function (Blueprint $table) {
            $table->dropIndex('domains_name_index');
        });
    }
};

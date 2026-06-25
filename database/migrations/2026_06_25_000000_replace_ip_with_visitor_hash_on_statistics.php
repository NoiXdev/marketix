<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Forward-only: dropping `ip` permanently destroys all stored visitor IPs
        // (the intended DSGVO cleanup). Do not casually roll this back in production.
        Schema::table('statistics', function (Blueprint $table) {
            $table->string('visitor_hash')->nullable()->after('url_id');
            $table->index(['url_id', 'visitor_hash', 'created_at'], 'statistics_url_visitor_hash_created_index');
        });

        // Drop the compound index that references ip before dropping the column.
        // Dropping the column also removes every full IP already stored.
        Schema::table('statistics', function (Blueprint $table) {
            // This compound index was created in 2026_06_11_000000_add_performance_indexes.php;
            // it must be dropped before `ip` can be removed, and down() recreates it.
            $table->dropIndex('statistics_url_ip_created_index');
            $table->dropColumn('ip');
        });
    }

    public function down(): void
    {
        Schema::table('statistics', function (Blueprint $table) {
            // Restores the column structurally; the original IP values cannot be recovered.
            $table->string('ip')->nullable()->after('url_id');
        });

        Schema::table('statistics', function (Blueprint $table) {
            $table->index(['url_id', 'ip', 'created_at'], 'statistics_url_ip_created_index');
            $table->dropIndex('statistics_url_visitor_hash_created_index');
            $table->dropColumn('visitor_hash');
        });
    }
};

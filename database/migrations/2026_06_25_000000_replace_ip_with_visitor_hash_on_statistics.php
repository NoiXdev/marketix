<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('statistics', function (Blueprint $table) {
            $table->string('visitor_hash')->nullable()->after('url_id');
            $table->index(['url_id', 'visitor_hash', 'created_at'], 'statistics_url_visitor_hash_created_index');
        });

        // Drop the compound index that references ip before dropping the column.
        // Dropping the column also removes every full IP already stored.
        Schema::table('statistics', function (Blueprint $table) {
            $table->dropIndex('statistics_url_ip_created_index');
            $table->dropColumn('ip');
        });
    }

    public function down(): void
    {
        Schema::table('statistics', function (Blueprint $table) {
            $table->string('ip')->nullable()->after('url_id');
        });

        Schema::table('statistics', function (Blueprint $table) {
            $table->index(['url_id', 'ip', 'created_at'], 'statistics_url_ip_created_index');
            $table->dropIndex('statistics_url_visitor_hash_created_index');
            $table->dropColumn('visitor_hash');
        });
    }
};

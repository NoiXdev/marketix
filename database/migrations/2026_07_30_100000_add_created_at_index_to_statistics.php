<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('statistics', function (Blueprint $table) {
            // The daily `statistics:prune` job scans `WHERE created_at < ?`. The
            // existing composite indexes all lead with url_id/project_id, so none
            // can serve a bare created_at range — this standalone index does.
            $table->index('created_at', 'statistics_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('statistics', function (Blueprint $table) {
            $table->dropIndex('statistics_created_at_index');
        });
    }
};

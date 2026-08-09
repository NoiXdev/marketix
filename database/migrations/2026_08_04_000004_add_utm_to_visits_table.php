<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->string('utm_source')->nullable()->after('referer_domain');
            $table->string('utm_medium')->nullable()->after('utm_source');
            $table->string('utm_campaign')->nullable()->after('utm_medium');
            $table->string('utm_term')->nullable()->after('utm_campaign');
            $table->string('utm_content')->nullable()->after('utm_term');
            $table->index(['site_id', 'started_at', 'utm_source'], 'visits_site_started_utm_source_index');
        });
    }

    public function down(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->dropIndex('visits_site_started_utm_source_index');
            $table->dropColumn(['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content']);
        });
    }
};

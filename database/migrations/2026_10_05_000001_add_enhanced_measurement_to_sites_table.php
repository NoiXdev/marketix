<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->boolean('track_outbound_links')->default(true)->after('respect_dnt');
            $table->boolean('track_file_downloads')->default(true)->after('track_outbound_links');
            $table->string('site_search_params')->nullable()->after('track_file_downloads');
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn(['track_outbound_links', 'track_file_downloads', 'site_search_params']);
        });
    }
};

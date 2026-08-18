<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crawl_pages', function (Blueprint $table) {
            $table->json('structured_data_items')->nullable()->after('hreflang');
        });
    }

    public function down(): void
    {
        Schema::table('crawl_pages', function (Blueprint $table) {
            $table->dropColumn('structured_data_items');
        });
    }
};

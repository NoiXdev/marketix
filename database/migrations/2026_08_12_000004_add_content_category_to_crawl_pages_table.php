<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crawl_pages', function (Blueprint $table) {
            $table->string('content_category')->nullable()->after('content_type')->index();
        });
    }

    public function down(): void
    {
        Schema::table('crawl_pages', function (Blueprint $table) {
            $table->dropIndex(['content_category']);
            $table->dropColumn('content_category');
        });
    }
};

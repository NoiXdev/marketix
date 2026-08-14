<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crawls', function (Blueprint $table) {
            $table->boolean('capture_screenshots')->default(false)->after('crawl_sitemap');
        });

        Schema::table('crawl_pages', function (Blueprint $table) {
            $table->string('screenshot_desktop_path')->nullable()->after('images_missing_alt');
            $table->string('screenshot_mobile_path')->nullable()->after('screenshot_desktop_path');
        });
    }

    public function down(): void
    {
        Schema::table('crawls', function (Blueprint $table) {
            $table->dropColumn('capture_screenshots');
        });

        Schema::table('crawl_pages', function (Blueprint $table) {
            $table->dropColumn(['screenshot_desktop_path', 'screenshot_mobile_path']);
        });
    }
};

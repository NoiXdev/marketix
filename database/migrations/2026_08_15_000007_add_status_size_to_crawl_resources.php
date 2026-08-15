<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crawl_resources', function (Blueprint $table) {
            $table->unsignedSmallInteger('status_code')->nullable()->after('is_internal');
            $table->unsignedInteger('size_bytes')->nullable()->after('status_code');
        });
    }

    public function down(): void
    {
        Schema::table('crawl_resources', function (Blueprint $table) {
            $table->dropColumn(['status_code', 'size_bytes']);
        });
    }
};

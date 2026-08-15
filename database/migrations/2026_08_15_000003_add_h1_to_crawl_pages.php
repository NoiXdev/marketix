<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crawl_pages', function (Blueprint $table) {
            $table->text('h1')->nullable()->after('meta_keywords');
        });
    }

    public function down(): void
    {
        Schema::table('crawl_pages', function (Blueprint $table) {
            $table->dropColumn('h1');
        });
    }
};

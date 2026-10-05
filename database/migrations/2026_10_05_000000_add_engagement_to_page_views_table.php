<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('page_views', function (Blueprint $table) {
            $table->unsignedInteger('engaged_seconds')->nullable()->after('language');
            $table->unsignedTinyInteger('scroll_depth')->nullable()->after('engaged_seconds');
        });
    }

    public function down(): void
    {
        Schema::table('page_views', function (Blueprint $table) {
            $table->dropColumn(['engaged_seconds', 'scroll_depth']);
        });
    }
};

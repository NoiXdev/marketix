<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('urls', function (Blueprint $table) {
            $table->json('targeting_geo')->nullable()->after('url');
            $table->json('targeting_device')->nullable()->after('targeting_geo');
            $table->json('targeting_language')->nullable()->after('targeting_device');
        });
    }

    public function down(): void
    {
        Schema::table('urls', function (Blueprint $table) {
            $table->dropColumn(['targeting_geo', 'targeting_device', 'targeting_language']);
        });
    }
};

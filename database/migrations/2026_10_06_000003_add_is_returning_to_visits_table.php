<?php

use App\Support\Analytics\ReturningVisits;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->boolean('is_returning')->default(false)->after('is_bot');
        });

        ReturningVisits::backfill();
    }

    public function down(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->dropColumn('is_returning');
        });
    }
};

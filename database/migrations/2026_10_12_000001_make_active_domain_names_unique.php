<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('domains')
            ->whereNull('deleted_at')
            ->selectRaw('lower(name) as host')
            ->groupByRaw('lower(name)')
            ->havingRaw('count(*) > 1')
            ->pluck('host');

        if ($duplicates->isNotEmpty()) {
            throw new RuntimeException('These domains are registered in more than one place: '.$duplicates->implode(', ').'. Delete the duplicates before running this migration.');
        }

        Schema::table('domains', function (Blueprint $table) {
            $table->string('active_name')->nullable()->virtualAs('case when deleted_at is null then name end');
            $table->unique('active_name');
        });
    }

    public function down(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->dropUnique(['active_name']);
            $table->dropColumn('active_name');
        });
    }
};

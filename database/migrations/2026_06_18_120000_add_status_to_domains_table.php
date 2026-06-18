<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->boolean('dns_ok')->nullable()->after('redirect_not_found');
            $table->boolean('reachable_ok')->nullable()->after('dns_ok');
            $table->boolean('ssl_ok')->nullable()->after('reachable_ok');
            $table->json('check_details')->nullable()->after('ssl_ok');
            $table->timestamp('last_checked_at')->nullable()->after('check_details');
        });
    }

    public function down(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->dropColumn(['dns_ok', 'reachable_ok', 'ssl_ok', 'check_details', 'last_checked_at']);
        });
    }
};

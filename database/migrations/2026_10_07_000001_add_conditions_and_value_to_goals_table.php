<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goals', function (Blueprint $table) {
            $table->json('conditions')->nullable()->after('match_value');
            $table->decimal('value', 12, 2)->nullable()->after('conditions');
            $table->char('currency', 3)->nullable()->after('value');
        });
    }

    public function down(): void
    {
        Schema::table('goals', function (Blueprint $table) {
            $table->dropColumn(['conditions', 'value', 'currency']);
        });
    }
};

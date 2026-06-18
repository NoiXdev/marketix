<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qr_codes', function (Blueprint $table) {
            $table->foreignUlid('url_id')->nullable()->after('project_id')
                ->constrained('urls')->nullOnDelete();
        });

        Schema::table('qr_codes', function (Blueprint $table) {
            $table->dropUnique('qr_codes_code_unique');
            $table->dropColumn(['code', 'scans']);
        });
    }

    public function down(): void
    {
        Schema::table('qr_codes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('url_id');
            $table->string('code', 12)->nullable()->unique();
            $table->unsignedBigInteger('scans')->default(0);
        });
    }
};

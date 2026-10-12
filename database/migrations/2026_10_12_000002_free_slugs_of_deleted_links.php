<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('urls', function (Blueprint $table) {
            $table->string('active_slug')->nullable()->virtualAs('case when deleted_at is null then slug end');
            $table->unique(['domain_id', 'active_slug']);
            $table->index(['domain_id', 'slug']);
        });

        Schema::table('urls', function (Blueprint $table) {
            $table->dropUnique(['domain_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::table('urls', function (Blueprint $table) {
            $table->unique(['domain_id', 'slug']);
        });

        Schema::table('urls', function (Blueprint $table) {
            $table->dropIndex(['domain_id', 'slug']);
            $table->dropUnique(['domain_id', 'active_slug']);
            $table->dropColumn('active_slug');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_views', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('visit_id')->constrained('visits')->cascadeOnDelete();
            $table->foreignUlid('site_id')->constrained('sites')->cascadeOnDelete();
            $table->foreignUlid('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('visitor_hash');
            $table->string('path');
            $table->longText('referer')->nullable();
            $table->string('referer_domain')->nullable();
            $table->string('country')->nullable();
            $table->char('country_code', 2)->nullable();
            $table->string('city')->nullable();
            $table->string('browser')->nullable();
            $table->string('os')->nullable();
            $table->string('device')->nullable();
            $table->string('language', 5)->nullable();
            $table->boolean('is_bot')->default(false);
            $table->timestamp('created_at')->nullable();

            $table->index(['site_id', 'created_at'], 'page_views_site_created_index');
            $table->index(['site_id', 'path'], 'page_views_site_path_index');
            $table->index('visit_id', 'page_views_visit_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_views');
    }
};

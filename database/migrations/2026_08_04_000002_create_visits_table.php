<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visits', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('site_id')->constrained('sites')->cascadeOnDelete();
            $table->foreignUlid('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('visitor_hash');
            $table->timestamp('started_at');
            $table->timestamp('last_activity_at');
            $table->unsignedInteger('pageview_count')->default(1);
            $table->string('entry_path');
            $table->string('exit_path');
            $table->char('country_code', 2)->nullable();
            $table->string('browser')->nullable();
            $table->string('os')->nullable();
            $table->string('device')->nullable();
            $table->string('referer_domain')->nullable();
            $table->boolean('is_bot')->default(false);
            $table->timestamps();

            $table->index(['site_id', 'visitor_hash', 'last_activity_at'], 'visits_site_visitor_activity_index');
            $table->index(['site_id', 'started_at'], 'visits_site_started_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visits');
    }
};

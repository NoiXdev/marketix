<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crawls', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('project_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('site_id')->nullable()->constrained()->nullOnDelete();
            $table->string('start_url');
            $table->string('mode')->default('full_site');
            $table->boolean('render_js')->default(false);
            $table->boolean('respect_robots')->default(true);
            $table->boolean('include_subdomains')->default(false);
            $table->unsignedInteger('delay_ms')->default(0);
            $table->unsignedInteger('max_pages')->nullable();
            $table->string('status')->default('queued')->index();
            $table->unsignedInteger('pages_crawled')->default(0);
            $table->text('error')->nullable();
            $table->json('summary')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crawls');
    }
};

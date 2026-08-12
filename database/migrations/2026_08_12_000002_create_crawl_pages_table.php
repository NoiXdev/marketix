<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crawl_pages', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('crawl_id')->constrained()->cascadeOnDelete();
            $table->text('url');
            $table->text('final_url')->nullable();
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->json('redirect_chain')->nullable();
            $table->string('content_type')->nullable();
            $table->unsignedInteger('response_time_ms')->nullable();
            $table->unsignedInteger('size_bytes')->nullable();
            $table->unsignedSmallInteger('depth')->nullable();
            $table->text('title')->nullable();
            $table->unsignedSmallInteger('title_length')->nullable();
            $table->text('meta_description')->nullable();
            $table->unsignedSmallInteger('meta_description_length')->nullable();
            $table->text('canonical')->nullable();
            $table->string('meta_robots')->nullable();
            $table->unsignedInteger('word_count')->nullable();
            $table->json('headings')->nullable();
            $table->boolean('is_indexable')->default(true);
            $table->string('indexability_reason')->nullable();
            $table->boolean('in_sitemap')->default(false);
            $table->unsignedInteger('inlinks_count')->default(0);
            $table->boolean('is_orphan')->default(false);
            $table->json('structured_data')->nullable();
            $table->json('images_missing_alt')->nullable();
            $table->json('issues')->nullable();
            $table->timestamps();
            $table->index(['crawl_id', 'status_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crawl_pages');
    }
};

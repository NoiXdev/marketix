<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crawl_links', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('crawl_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('from_page_id')->constrained('crawl_pages')->cascadeOnDelete();
            $table->text('to_url');
            $table->string('type')->default('internal'); // internal | external
            $table->text('anchor')->nullable();
            $table->string('rel')->nullable();
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->timestamps();
            $table->index(['crawl_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crawl_links');
    }
};

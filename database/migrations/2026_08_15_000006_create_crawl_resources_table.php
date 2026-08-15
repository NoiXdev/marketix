<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crawl_resources', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('crawl_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('from_page_id')->constrained('crawl_pages')->cascadeOnDelete();
            $table->text('url');
            $table->string('type')->default('other'); // javascript | css | font | image | other
            $table->boolean('is_internal')->default(true);
            $table->timestamps();
            $table->index(['crawl_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crawl_resources');
    }
};

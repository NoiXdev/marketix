<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pixel_url', function (Blueprint $table) {
            $table->foreignUlid('pixel_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('url_id')->constrained()->cascadeOnDelete();
            $table->primary(['pixel_id', 'url_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pixel_url');
    }
};

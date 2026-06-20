<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qr_code_versions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('qr_code_id')->constrained('qr_codes')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('name');
            $table->string('type', 30);
            $table->boolean('is_dynamic')->default(false);
            $table->json('content');
            $table->json('style');
            $table->foreignUlid('domain_id')->nullable();
            $table->string('slug')->nullable();
            $table->foreignUlid('created_by')->nullable();
            $table->timestamps();

            $table->unique(['qr_code_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qr_code_versions');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qr_templates', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('project_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->json('style');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qr_templates');
    }
};

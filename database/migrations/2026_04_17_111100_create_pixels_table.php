<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pixels', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('project_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 50);
            $table->string('name', 255);
            $table->string('tag', 500);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pixels');
    }
};

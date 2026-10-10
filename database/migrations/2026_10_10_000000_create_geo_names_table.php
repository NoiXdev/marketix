<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('geo_names', function (Blueprint $table) {
            $table->id();
            $table->string('type', 16);
            $table->char('country_code', 2);
            $table->string('name');
            $table->json('names');
            $table->timestamps();

            $table->unique(['type', 'country_code', 'name'], 'geo_names_place_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('geo_names');
    }
};

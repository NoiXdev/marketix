<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goals', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('site_id')->constrained('sites')->cascadeOnDelete();
            $table->foreignUlid('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('name');
            $table->string('type', 20);
            $table->string('match_value');
            $table->timestamps();
            $table->softDeletes();

            $table->index('site_id', 'goals_site_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goals');
    }
};

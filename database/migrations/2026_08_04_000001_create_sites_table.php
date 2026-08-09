<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sites', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('name');
            $table->string('domain');
            $table->string('tracking_id', 32)->unique();
            $table->string('tracking_mode', 20)->default('cookieless');
            $table->string('consent_mode', 30)->default('immediate');
            $table->string('consent_signal')->nullable();
            $table->boolean('respect_dnt')->default(false);
            $table->unsignedInteger('retention_days')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('project_id', 'sites_project_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sites');
    }
};

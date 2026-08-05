<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('visit_id')->constrained('visits')->cascadeOnDelete();
            $table->foreignUlid('site_id')->constrained('sites')->cascadeOnDelete();
            $table->foreignUlid('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('visitor_hash');
            $table->string('name');
            $table->json('props')->nullable();
            $table->string('path');
            $table->boolean('is_bot')->default(false);
            $table->timestamp('created_at')->nullable();

            $table->index(['site_id', 'created_at'], 'events_site_created_index');
            $table->index(['site_id', 'name'], 'events_site_name_index');
            $table->index('visit_id', 'events_visit_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};

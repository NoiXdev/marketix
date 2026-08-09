<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduled_reports', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('project_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('type');
            $table->ulid('subject_id')->nullable();
            $table->string('frequency');
            $table->unsignedTinyInteger('weekday')->nullable();
            $table->unsignedTinyInteger('day_of_month')->nullable();
            $table->string('period');
            $table->json('formats');
            $table->json('recipients');
            $table->boolean('active')->default(true);
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamp('next_run_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_reports');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qr_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects');
            $table->string('name');
            $table->string('type', 30)->default('link');
            $table->boolean('is_dynamic')->default(true);
            $table->string('code', 12)->nullable()->unique(); // dynamic redirect token
            $table->json('content');
            $table->json('style');
            $table->unsignedBigInteger('scans')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qr_codes');
    }
};

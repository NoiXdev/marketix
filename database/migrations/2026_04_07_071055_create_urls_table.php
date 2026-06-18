<?php

use App\Enums\UrlStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('urls', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('project_id')->constrained('projects');
            $table->foreignUlid('domain_id')->constrained('domains');
            $table->foreignUlid('user_id')->constrained('users');
            $table->string('slug');
            $table->string('url');
            $table->tinyInteger('type');
            $table->string('password')->nullable();
            $table->dateTime('expired_at')->nullable();
            $table->unsignedBigInteger('clicks')->default(0);
            $table->unsignedBigInteger('unique_clicks')->default(0);
            $table->tinyInteger('status')->default(UrlStatus::ACTIVATED);
            $table->boolean('archived')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['domain_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('urls');
    }
};

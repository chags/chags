<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_provider_settings', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('provider', 40);
            $table->boolean('enabled')->default(true);
            $table->boolean('is_default')->default(false)->index();
            $table->string('base_url')->nullable();
            $table->string('model');
            $table->text('api_key')->nullable();
            $table->string('organization')->nullable();
            $table->unsignedSmallInteger('timeout')->default(60);
            $table->unsignedInteger('max_output_tokens')->default(4096);
            $table->decimal('temperature', 3, 2)->default(0.20);
            $table->timestamp('last_tested_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_provider_settings');
    }
};

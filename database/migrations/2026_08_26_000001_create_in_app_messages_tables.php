<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('in_app_messages', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('type', 40)->default('administrative')->index();
            $table->string('status', 20)->default('draft')->index();
            $table->string('title', 150);
            $table->text('body')->nullable();
            $table->text('sensitive_payload')->nullable();
            $table->string('audience', 20)->default('user');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source_type')->nullable();
            $table->string('source_id')->nullable();
            $table->timestampTz('scheduled_at')->nullable()->index();
            $table->timestampTz('published_at')->nullable()->index();
            $table->timestampTz('expires_at')->nullable()->index();
            $table->timestampsTz();
            $table->softDeletesTz();
            $table->index(['source_type', 'source_id']);
        });

        Schema::create('in_app_message_recipients', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('message_id')->constrained('in_app_messages')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestampTz('read_at')->nullable()->index();
            $table->timestampTz('dismissed_at')->nullable();
            $table->timestampsTz();
            $table->unique(['message_id', 'user_id']);
            $table->index(['user_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('in_app_message_recipients');
        Schema::dropIfExists('in_app_messages');
    }
};

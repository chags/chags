<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('whatsapp_phone', 20)->nullable()->unique()->after('phone');
            $table->timestampTz('whatsapp_phone_verified_at')->nullable();
            $table->timestampTz('whatsapp_phone_changed_at')->nullable();
            $table->boolean('app_unlock_required')->default(true);
            $table->timestampTz('first_app_access_completed_at')->nullable();
            $table->timestampTz('jwt_invalid_before')->nullable();
        });

        Schema::create('whatsapp_unlock_challenges', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->char('phone_hash', 64)->index();
            $table->uuid('device_installation_id')->index();
            $table->string('code_hash')->nullable();
            $table->timestampTz('expires_at')->index();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestampTz('consumed_at')->nullable();
            $table->string('provider_message_id')->nullable();
            $table->char('request_ip_hash', 64)->nullable();
            $table->timestampsTz();
        });

        Schema::create('api_devices', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('installation_id');
            $table->string('name')->nullable();
            $table->text('public_key');
            $table->string('key_algorithm', 20)->default('ES256');
            $table->char('key_fingerprint', 64)->unique();
            $table->string('platform', 20);
            $table->string('app_version', 30);
            $table->string('app_build', 30);
            $table->string('package_name');
            $table->string('signing_digest')->nullable();
            $table->string('manufacturer')->nullable();
            $table->string('model')->nullable();
            $table->string('os_version')->nullable();
            $table->date('security_patch')->nullable();
            $table->string('locale', 20)->nullable();
            $table->string('timezone', 60)->nullable();
            $table->boolean('biometric_available')->default(false);
            $table->string('attestation_provider', 30);
            $table->string('attestation_status', 30)->default('pending');
            $table->string('risk_level', 20)->default('high');
            $table->string('status', 40)->default('face_verification_required');
            $table->timestampTz('face_verified_at')->nullable();
            $table->string('face_verification_version')->nullable();
            $table->timestampTz('first_seen_at');
            $table->timestampTz('last_seen_at');
            $table->string('last_ip', 45)->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->unique(['user_id', 'installation_id']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('device_challenges', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('installation_id')->index();
            $table->string('purpose', 30);
            $table->char('nonce_hash', 64);
            $table->timestampTz('expires_at')->index();
            $table->timestampTz('consumed_at')->nullable();
            $table->string('request_ip', 45)->nullable();
            $table->timestampsTz();
        });

        Schema::create('faceio_identities', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('facial_id_encrypted');
            $table->char('facial_id_hash', 64)->unique();
            $table->timestampTz('enrolled_at');
            $table->softDeletesTz();
            $table->timestampsTz();
        });

        Schema::create('faceio_sessions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('api_device_id')->constrained('api_devices')->cascadeOnDelete();
            $table->string('purpose', 30)->default('first_enrollment');
            $table->char('opaque_payload_hash', 64)->unique();
            $table->char('facial_id_hash', 64)->nullable()->index();
            $table->string('status', 20)->default('pending');
            $table->timestampTz('expires_at')->index();
            $table->timestampTz('confirmed_at')->nullable();
            $table->timestampTz('consumed_at')->nullable();
            $table->timestampsTz();
        });

        Schema::create('integration_webhook_events', function (Blueprint $table): void {
            $table->id();
            $table->string('provider', 30);
            $table->char('external_event_id', 64);
            $table->string('event_type', 40);
            $table->char('payload_hash', 64);
            $table->string('status', 20)->default('received');
            $table->timestampTz('received_at');
            $table->timestampTz('processed_at')->nullable();
            $table->string('error_code')->nullable();
            $table->timestampsTz();
            $table->unique(['provider', 'external_event_id']);
        });

        Schema::create('api_idempotency_keys', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('route');
            $table->string('method', 10);
            $table->string('idempotency_key');
            $table->char('request_hash', 64);
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->json('response_body')->nullable();
            $table->timestampTz('expires_at')->index();
            $table->timestampsTz();
            $table->unique(['user_id', 'route', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_idempotency_keys');
        Schema::dropIfExists('integration_webhook_events');
        Schema::dropIfExists('faceio_sessions');
        Schema::dropIfExists('faceio_identities');
        Schema::dropIfExists('device_challenges');
        Schema::dropIfExists('api_devices');
        Schema::dropIfExists('whatsapp_unlock_challenges');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'whatsapp_phone', 'whatsapp_phone_verified_at', 'whatsapp_phone_changed_at',
                'app_unlock_required', 'first_app_access_completed_at', 'jwt_invalid_before',
            ]);
        });
    }
};

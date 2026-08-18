<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('provider')->default('google');
            $table->string('provider_email')->nullable();
            $table->string('calendar_id')->default('primary');
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->string('status')->default('disconnected');
            $table->timestamp('last_tested_at')->nullable();
            $table->timestamps();
        });

        Schema::create('interview_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stage_id')->constrained('recruitment_stages')->restrictOnDelete();
            $table->foreignId('organizer_id')->constrained('users')->restrictOnDelete();
            $table->string('format', 20);
            $table->string('provider', 20)->default('manual');
            $table->string('status', 30)->default('scheduled');
            $table->string('title');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('timezone', 60)->default('America/Sao_Paulo');
            $table->string('location')->nullable();
            $table->text('meeting_url')->nullable();
            $table->string('provider_event_id')->nullable()->unique();
            $table->text('provider_event_url')->nullable();
            $table->text('public_instructions')->nullable();
            $table->text('internal_notes')->nullable();
            $table->string('candidate_response', 30)->default('pending');
            $table->timestamp('candidate_responded_at')->nullable();
            $table->text('reschedule_reason')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['application_id', 'stage_id']);
            $table->index(['starts_at', 'status']);
        });

        Schema::create('interview_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('interview_schedule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('role', 20)->default('interviewer');
            $table->string('response_status', 20)->default('pending');
            $table->timestamps();
        });

        Schema::create('interview_notification_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('interview_schedule_id')->constrained()->cascadeOnDelete();
            $table->string('recipient');
            $table->string('channel', 30);
            $table->string('type', 30)->default('invitation');
            $table->string('status', 30);
            $table->string('provider_id')->nullable();
            $table->unsignedSmallInteger('attempts')->default(1);
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interview_notification_deliveries');
        Schema::dropIfExists('interview_participants');
        Schema::dropIfExists('interview_schedules');
        Schema::dropIfExists('calendar_connections');
    }
};

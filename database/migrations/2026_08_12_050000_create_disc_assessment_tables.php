<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disc_questions', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->unsignedSmallInteger('position');
            $table->text('prompt');
            $table->boolean('active')->default(true);
            $table->string('version', 20)->default('1.0');
            $table->timestamps();
            $table->unique(['version', 'position']);
        });

        Schema::create('disc_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('disc_question_id')->constrained()->cascadeOnDelete();
            $table->string('code')->unique();
            $table->text('text');
            $table->char('dimension', 1);
            $table->unsignedSmallInteger('weight')->default(1);
            $table->unsignedSmallInteger('display_order');
            $table->timestamps();
        });

        Schema::create('disc_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('candidate_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 20)->default('in_progress')->index();
            $table->string('questionnaire_version', 20)->default('1.0');
            $table->unsignedSmallInteger('current_position')->default(1);
            $table->unsignedSmallInteger('d_score')->default(0);
            $table->unsignedSmallInteger('i_score')->default(0);
            $table->unsignedSmallInteger('s_score')->default(0);
            $table->unsignedSmallInteger('c_score')->default(0);
            $table->string('dominant_profile', 4)->nullable();
            $table->char('secondary_profile', 1)->nullable();
            $table->json('result_snapshot')->nullable();
            $table->timestamp('consent_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('disc_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('disc_assessment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('disc_question_id')->constrained()->restrictOnDelete();
            $table->foreignId('disc_option_id')->constrained()->restrictOnDelete();
            $table->timestamp('answered_at');
            $table->timestamps();
            $table->unique(['disc_assessment_id', 'disc_question_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disc_answers');
        Schema::dropIfExists('disc_assessments');
        Schema::dropIfExists('disc_options');
        Schema::dropIfExists('disc_questions');
    }
};

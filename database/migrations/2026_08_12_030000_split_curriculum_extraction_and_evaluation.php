<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('curricula', function (Blueprint $table) {
            $table->string('extraction_status', 20)->default('pending')->index();
            $table->string('evaluation_status', 20)->default('pending')->index();
            $table->longText('extracted_text')->nullable();
            $table->text('extraction_error')->nullable();
            $table->text('evaluation_error')->nullable();
            $table->string('recommendation', 30)->nullable();
            $table->text('opinion')->nullable();
            $table->json('strengths')->nullable();
            $table->json('concerns')->nullable();
            $table->unsignedSmallInteger('extraction_attempts')->default(0);
            $table->unsignedSmallInteger('evaluation_attempts')->default(0);
            $table->timestamp('extracted_at')->nullable();
            $table->timestamp('evaluated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('curricula', function (Blueprint $table) {
            $table->dropColumn([
                'extraction_status', 'evaluation_status', 'extracted_text',
                'extraction_error', 'evaluation_error', 'recommendation',
                'opinion', 'strengths', 'concerns', 'extraction_attempts',
                'evaluation_attempts', 'extracted_at', 'evaluated_at',
            ]);
        });
    }
};

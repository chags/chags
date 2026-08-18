<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->string('resume_path')->nullable()->after('cover_letter');
            $table->string('resume_original_name')->nullable()->after('resume_path');
            $table->string('resume_mime_type', 120)->nullable()->after('resume_original_name');
            $table->unsignedBigInteger('resume_size')->nullable()->after('resume_mime_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn(['resume_path', 'resume_original_name', 'resume_mime_type', 'resume_size']);
        });
    }
};

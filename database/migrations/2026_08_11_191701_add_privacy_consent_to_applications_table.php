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
            $table->timestamp('privacy_consent_at')->nullable()->after('resume_size');
            $table->string('privacy_consent_version', 30)->nullable()->after('privacy_consent_at');
            $table->string('privacy_consent_ip', 45)->nullable()->after('privacy_consent_version');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn(['privacy_consent_at', 'privacy_consent_version', 'privacy_consent_ip']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_provider_settings', function (Blueprint $table) {
            $table->boolean('last_test_succeeded')->nullable()->after('last_tested_at');
        });
    }

    public function down(): void
    {
        Schema::table('ai_provider_settings', function (Blueprint $table) {
            $table->dropColumn('last_test_succeeded');
        });
    }
};

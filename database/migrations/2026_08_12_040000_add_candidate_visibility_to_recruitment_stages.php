<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recruitment_stages', function (Blueprint $table) {
            $table->boolean('candidate_visible')->default(true);
            $table->string('public_name')->nullable();
            $table->text('public_description')->nullable();
            $table->string('candidate_action')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('recruitment_stages', function (Blueprint $table) {
            $table->dropColumn(['candidate_visible', 'public_name', 'public_description', 'candidate_action']);
        });
    }
};

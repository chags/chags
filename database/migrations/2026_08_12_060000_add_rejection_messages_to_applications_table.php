<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->text('rejection_message')->nullable()->after('rejected_at');
            $table->text('rejection_internal_notes')->nullable()->after('rejection_message');
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn(['rejection_message', 'rejection_internal_notes']);
        });
    }
};

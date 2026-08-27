<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('DROP INDEX IF EXISTS time_entries_active_daily_type_unique');

        Schema::table('time_entries', function (Blueprint $table): void {
            $table->foreignId('absence_justification_id')
                ->nullable()
                ->after('time_adjustment_request_id')
                ->constrained()
                ->nullOnDelete();
        });

        DB::statement("CREATE UNIQUE INDEX time_entries_active_daily_type_unique ON time_entries (user_id, work_date, type) WHERE status <> 'cancelled'");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS time_entries_active_daily_type_unique');

        Schema::table('time_entries', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('absence_justification_id');
        });

        DB::statement("CREATE UNIQUE INDEX time_entries_active_daily_type_unique ON time_entries (user_id, work_date, type) WHERE status <> 'cancelled'");
    }
};

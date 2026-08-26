<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('time_entries', function (Blueprint $table): void {
            $table->date('work_date')->nullable()->after('recorded_at')->index();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                'UPDATE time_entries SET work_date = (recorded_at AT TIME ZONE ?)::date WHERE work_date IS NULL',
                [config('app.business_timezone')],
            );
            DB::statement('ALTER TABLE time_entries ALTER COLUMN work_date SET NOT NULL');
        } else {
            DB::statement('UPDATE time_entries SET work_date = date(recorded_at) WHERE work_date IS NULL');
        }
        DB::statement("CREATE UNIQUE INDEX time_entries_active_daily_type_unique ON time_entries (user_id, work_date, type) WHERE status <> 'cancelled'");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS time_entries_active_daily_type_unique');
        Schema::table('time_entries', function (Blueprint $table): void {
            $table->dropColumn('work_date');
        });
    }
};

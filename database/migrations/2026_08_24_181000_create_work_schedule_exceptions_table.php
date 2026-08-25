<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_schedule_exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('work_date');
            $table->string('type', 30);
            $table->time('start_time')->nullable();
            $table->time('break_start_time')->nullable();
            $table->time('break_end_time')->nullable();
            $table->time('end_time')->nullable();
            $table->unsignedInteger('expected_minutes')->default(0);
            $table->text('reason');
            $table->string('status', 20)->default('approved');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'work_date']);
            $table->index(['work_date', 'status']);
        });

        Schema::table('hour_bank_transactions', function (Blueprint $table) {
            $table->foreignId('work_schedule_exception_id')
                ->nullable()
                ->after('time_adjustment_request_id')
                ->constrained()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('hour_bank_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('work_schedule_exception_id');
        });
        Schema::dropIfExists('work_schedule_exceptions');
    }
};

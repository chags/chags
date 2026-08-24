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
        Schema::create('employee_work_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->json('weekdays');
            $table->time('start_time');
            $table->time('break_start_time')->nullable();
            $table->time('break_end_time')->nullable();
            $table->time('end_time');
            $table->unsignedSmallInteger('daily_minutes');
            $table->unsignedSmallInteger('weekly_minutes');
            $table->date('valid_from');
            $table->date('valid_until')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->index(['user_id', 'valid_from', 'valid_until'], 'work_schedules_user_validity_index');
            $table->index('active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_work_schedules');
    }
};

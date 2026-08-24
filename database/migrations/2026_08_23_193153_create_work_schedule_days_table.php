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
        Schema::create('work_schedule_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_schedule_group_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('day_index');
            $table->string('label', 50);
            $table->boolean('is_workday')->default(true);
            $table->time('start_time')->nullable();
            $table->time('break_start_time')->nullable();
            $table->time('break_end_time')->nullable();
            $table->time('end_time')->nullable();
            $table->unsignedSmallInteger('expected_minutes')->default(0);
            $table->timestamps();
            $table->unique(['work_schedule_group_id', 'day_index'], 'work_schedule_days_group_day_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_schedule_days');
    }
};

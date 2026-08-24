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
        Schema::create('work_schedule_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->string('schedule_type', 20);
            $table->unsignedSmallInteger('weekly_minutes')->default(2640);
            $table->unsignedTinyInteger('entry_tolerance_minutes')->default(5);
            $table->unsignedTinyInteger('daily_tolerance_minutes')->default(10);
            $table->unsignedTinyInteger('operational_window_minutes')->default(10);
            $table->unsignedSmallInteger('daily_overtime_limit_minutes')->default(120);
            $table->boolean('requires_overtime_approval')->default(true);
            $table->date('cycle_start_date')->nullable();
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['schedule_type', 'active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_schedule_groups');
    }
};

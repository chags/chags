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
        Schema::create('vacation_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('accrual_start');
            $table->date('accrual_end');
            $table->unsignedTinyInteger('entitled_days')->default(30);
            $table->unsignedTinyInteger('used_days')->default(0);
            $table->date('scheduled_start')->nullable();
            $table->date('scheduled_end')->nullable();
            $table->string('status', 20)->default('accruing');
            $table->timestamps();
            $table->unique(['user_id', 'accrual_start', 'accrual_end'], 'vacation_periods_user_accrual_unique');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vacation_periods');
    }
};

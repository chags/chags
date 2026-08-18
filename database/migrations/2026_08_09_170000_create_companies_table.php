<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('headquarters_id')->nullable()->constrained('companies')->restrictOnDelete();
            $table->string('unit_type', 20);
            $table->string('unit_number', 30)->unique();
            $table->string('unit_name');
            $table->string('name');
            $table->string('trade_name')->nullable();
            $table->char('cnpj', 14)->unique();
            $table->string('logo')->nullable();
            $table->string('address');
            $table->string('address_number', 30);
            $table->string('address_complement')->nullable();
            $table->string('district');
            $table->string('city');
            $table->char('state', 2);
            $table->char('postal_code', 8);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};

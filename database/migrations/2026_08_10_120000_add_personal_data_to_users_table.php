<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('cpf', 11)->nullable()->unique()->after('email');
            $table->date('birth_date')->nullable()->after('cpf');
            $table->string('phone', 20)->nullable()->after('birth_date');
            $table->string('gender', 30)->nullable()->after('phone');
            $table->string('postal_code', 8)->nullable()->after('avatar');
            $table->string('address')->nullable()->after('postal_code');
            $table->string('address_number', 30)->nullable()->after('address');
            $table->string('address_complement')->nullable()->after('address_number');
            $table->string('district')->nullable()->after('address_complement');
            $table->string('city')->nullable()->after('district');
            $table->string('state', 2)->nullable()->after('city');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['cpf']);
            $table->dropColumn([
                'cpf', 'birth_date', 'phone', 'gender', 'postal_code', 'address',
                'address_number', 'address_complement', 'district', 'city', 'state',
            ]);
        });
    }
};

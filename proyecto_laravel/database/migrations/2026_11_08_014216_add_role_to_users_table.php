<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rol de la cuenta (RF01: CLIENTE, ARQUITECTO, AUDITOR o ADMINISTRADOR).
     *
     * El registro público solo asigna CLIENTE (RF14); la gestión de roles
     * internos corresponde a RF16/EP02. Se modela como una columna única
     * porque una cuenta tiene un solo rol en esta versión.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 30)->default('cliente')->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};

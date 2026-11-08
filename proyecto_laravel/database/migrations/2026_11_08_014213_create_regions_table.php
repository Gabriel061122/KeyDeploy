<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla de regiones del catálogo (contrato de integración EP03/RF12).
     *
     * Este módulo (EP04) solo necesita poder validar que una región existe
     * y que un producto está disponible en ella. El CRUD del catálogo queda
     * a cargo del miembro responsable de EP03.
     */
    public function up(): void
    {
        Schema::create('regions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 100);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regions');
    }
};

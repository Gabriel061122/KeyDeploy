<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla de productos del catálogo (contrato de integración EP03/RF12).
     *
     * Tipos contemplados por RF12: computación, almacenamiento, bases de
     * datos, redes, seguridad, copias de seguridad, monitorización,
     * computación IA, servicio IA y soporte.
     *
     * El módulo EP04 (proyectos y arquitecturas) solo requiere comprobar
     * existencia, tipo, estado activo y disponibilidad por región. El CRUD
     * del catálogo y sus atributos específicos corresponden a EP03.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 150);
            $table->string('type', 50);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};

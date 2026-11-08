<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Disponibilidad de un producto en una región (contrato de integración
     * EP03/RF12 y base de la regla RN05: no se puede contratar un producto
     * no disponible en la región exigida).
     *
     * Las eliminaciones quedan restringidas para no perder registros de
     * disponibilidad por accidente; la retirada de un producto se gestiona
     * con el estado `active`, no borrándolo.
     */
    public function up(): void
    {
        Schema::create('product_region', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('region_id')->constrained()->restrictOnDelete();
            $table->timestamps();

            $table->unique(['product_id', 'region_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_region');
    }
};

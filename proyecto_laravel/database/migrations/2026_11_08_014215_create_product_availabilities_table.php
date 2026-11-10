<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla product_availabilities: disponibilidad y precios por región (sección 5.1 UT2).
     * Tabla pivote con datos: precio en céntimos para evitar errores de redondeo (ADR-001).
     * Atributos: product_id (FK cascade), region_id (FK restrict), available (boolean default true),
     * price_cents (unsigned int), pricing_model (string/enum PricingModel).
     * Restricción única: unique(product_id, region_id).
     */
    public function up(): void
    {
        Schema::create('product_availabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('region_id')->constrained('regions')->restrictOnDelete();
            $table->foreignId('provider_id')->nullable()->constrained('providers')->nullOnDelete();
            $table->boolean('available')->default(true);
            $table->unsignedInteger('price_cents')->default(0);
            $table->string('pricing_model')->default('fijo_mensual');
            $table->timestamps();

            $table->unique(['product_id', 'region_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_availabilities');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Elementos de una arquitectura: producto del catálogo, cantidad y
     * configuración JSON individual (recuperada/almazenada mediante el
     * cast `json` de Eloquent).
     *
     * Los elementos nunca se eliminan con la arquitectura sin que se
     * verifique antes toda la coherencia de la operación.
     */
    public function up(): void
    {
        Schema::create('architecture_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('architecture_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->json('config')->default('{}');
            $table->timestamps();

            $table->index('architecture_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('architecture_items');
    }
};

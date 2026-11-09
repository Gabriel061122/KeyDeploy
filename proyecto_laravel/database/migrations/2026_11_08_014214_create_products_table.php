<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla products: servicios/productos del catálogo (sección 5.1 UT2).
     * Atributos: provider_id (FK restrict), name(100), description(text NULL),
     * type(string/enum), billing_unit(string), config(json NOT NULL), active(boolean default true).
     * Índice: (type, active).
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained('providers')->restrictOnDelete();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->string('type');
            $table->string('billing_unit');
            $table->json('config');
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['type', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};

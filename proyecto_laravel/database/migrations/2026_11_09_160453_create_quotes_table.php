<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cabecera de presupuesto emitido (snapshot histórico).
     * Almacena totales congelados por tipo de tarifa (mensual vs pago único).
     */
    public function up(): void
    {
        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('emitido');
            $table->unsignedBigInteger('subtotal_monthly_cents')->default(0);
            $table->unsignedBigInteger('subtotal_one_time_cents')->default(0);
            $table->unsignedBigInteger('total_cents')->default(0);
            $table->timestamp('issued_at')->useCurrent();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quotes');
    }
};

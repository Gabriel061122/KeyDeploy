<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cargo único por unidad de un producto en una región. Es independiente
     * de la tarifa recurrente (`fijo_mensual`, `por_hora`, `por_gb`), por lo
     * que se conserva en una columna propia y no como cuarta modalidad.
     */
    public function up(): void
    {
        Schema::table('product_availabilities', function (Blueprint $table) {
            $table->unsignedInteger('one_time_price_cents')->default(0)->after('price_cents');
        });
    }

    public function down(): void
    {
        Schema::table('product_availabilities', function (Blueprint $table) {
            $table->dropColumn('one_time_price_cents');
        });
    }
};

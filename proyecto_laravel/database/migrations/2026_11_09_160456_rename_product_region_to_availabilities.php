<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Renombrar product_region a product_availabilities y añadir columnas
     * para proveedor, disponibilidad, precio en céntimos y modelo de tarifa.
     *
     * Esta migración depende de create_providers_table (ejecutada antes por
     * el timestamp) para que la FK provider_id sea válida.
     */
    public function up(): void
    {
        if (Schema::hasTable('product_region') && ! Schema::hasTable('product_availabilities')) {
            Schema::rename('product_region', 'product_availabilities');
        }

        if (Schema::hasTable('product_availabilities')) {
            Schema::table('product_availabilities', function (Blueprint $table) {
                if (! Schema::hasColumn('product_availabilities', 'provider_id')) {
                    $table->foreignId('provider_id')->nullable()->after('region_id')->constrained()->nullOnDelete();
                }
                if (! Schema::hasColumn('product_availabilities', 'available')) {
                    $table->boolean('available')->default(true)->after('provider_id');
                }
                if (! Schema::hasColumn('product_availabilities', 'price_cents')) {
                    $table->unsignedBigInteger('price_cents')->default(0)->after('available');
                }
                if (! Schema::hasColumn('product_availabilities', 'pricing_model')) {
                    $table->string('pricing_model', 20)->default('mensual')->after('price_cents');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_availabilities', function (Blueprint $table) {
            $table->dropForeign(['provider_id']);
            $table->dropColumn(['provider_id', 'available', 'price_cents', 'pricing_model']);
        });

        Schema::rename('product_availabilities', 'product_region');
    }
};

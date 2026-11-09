<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Arquitecturas manuales asociadas a un Proyecto: región seleccionada
     * y procedencia (`manual` en este módulo; `ia` corresponderá al
     * flujo de propuestas RF04).
     */
    public function up(): void
    {
        Schema::create('architectures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->foreignId('region_id')->constrained()->restrictOnDelete();
            $table->string('name', 150);
            $table->string('source', 20)->default('manual');
            $table->timestamps();

            $table->index('project_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('architectures');
    }
};

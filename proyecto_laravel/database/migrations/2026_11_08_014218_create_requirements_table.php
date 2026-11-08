<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Requerimientos estructurados de un Proyecto (RF03): campo,
     * operador, valor y condición obligatoria u opcional, además de la
     * procedencia (`manual` o `ia`) que no puede falsear el cliente.
     *
     * La clave externa hacia `projects` no elimina en cascada: borrar un
     * proyecto no debe destruir sus requerimientos. Al ser el borrado del
     * proyecto lógico (SoftDeletes), esta restricción solo actúa como
     * último seguro de coherencia.
     */
    public function up(): void
    {
        Schema::create('requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->string('field_key', 100);
            $table->string('operator', 20);
            $table->string('value', 500);
            $table->string('data_type', 20);
            $table->boolean('mandatory')->default(false);
            $table->string('source', 20)->default('manual');
            $table->timestamps();

            $table->index('project_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('requirements');
    }
};

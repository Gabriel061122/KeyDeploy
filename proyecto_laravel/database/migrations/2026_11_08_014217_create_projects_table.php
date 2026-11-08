<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Proyectos del CLIENTE (RF02/CU02): nombre, descripción funcional,
     * región, presupuesto máximo y número esperado de usuarios.
     *
     * - El propietario se fija siempre en el servidor a partir del usuario
     *   autenticado (RF17/RN09).
     * - La región es una clave externa hacia el catálogo para garantizar
     *   la validez de la región asignada.
     * - Borrado lógico con SoftDeletes: eliminar un proyecto no debe
     *   destruir requerimientos, arquitecturas ni histórico asociado.
     */
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('region_id')->constrained()->restrictOnDelete();
            $table->string('name', 150);
            $table->text('description');
            $table->decimal('max_budget', 12, 2);
            $table->unsignedInteger('expected_users');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};

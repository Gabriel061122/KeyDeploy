<?php

namespace App\Enums;

/**
 * Tipos de producto del catálogo según la sección 5.1 y 6.1 de la plantilla UT2.
 * Contempla los 6 tipos mínimos exigidos por UT2 y variantes compatibles del análisis UT1.
 */
enum ProductType: string
{
    case Computacion = 'computacion';
    case Almacenamiento = 'almacenamiento';
    case BasesDeDatos = 'bases_de_datos';
    case BaseDatos = 'base_datos';
    case Redes = 'redes';
    case Red = 'red';
    case Seguridad = 'seguridad';
    case Backup = 'backup';
    case Monitorizacion = 'monitorizacion';
    case ComputacionIa = 'computacion_ia';
    case ServicioIa = 'servicio_ia';
    case Soporte = 'soporte';

    /**
     * Etiqueta legible para la interfaz de usuario.
     */
    public function label(): string
    {
        return match ($this) {
            self::Computacion => 'Computación',
            self::Almacenamiento => 'Almacenamiento',
            self::BasesDeDatos, self::BaseDatos => 'Bases de datos',
            self::Redes, self::Red => 'Redes',
            self::Seguridad => 'Seguridad',
            self::Backup => 'Copias de seguridad',
            self::Monitorizacion => 'Monitorización',
            self::ComputacionIa => 'Cómputo IA',
            self::ServicioIa => 'Servicio IA',
            self::Soporte => 'Soporte',
        };
    }

    /**
     * Reglas de validación para la configuración JSON (`config`) según el tipo (ADR-002).
     *
     * @return array<string, list<string>>
     */
    public function configRules(): array
    {
        return match ($this) {
            self::Computacion, self::ComputacionIa => [
                'config.vcpu' => ['required', 'integer', 'min:1'],
                'config.ram_gb' => ['required', 'integer', 'min:1'],
            ],
            self::Almacenamiento => [
                'config.capacity_gb' => ['required', 'integer', 'min:1'],
                'config.storage_type' => ['required', 'string'],
            ],
            self::BasesDeDatos, self::BaseDatos => [
                'config.engine' => ['required', 'string'],
                'config.storage_gb' => ['required', 'integer', 'min:10'],
            ],
            self::Redes, self::Red => [
                'config.bandwidth_mbps' => ['required', 'integer', 'min:10'],
            ],
            self::Seguridad => [
                'config.rules_count' => ['required', 'integer', 'min:1'],
            ],
            self::Backup => [
                'config.retention_days' => ['required', 'integer', 'min:1'],
            ],
            default => [],
        };
    }
}

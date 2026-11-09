<?php

namespace App\Enums;

/**
 * Modelos de tarificación para la tabla product_availabilities (sección 5.1 y 7.2 UT2).
 */
enum PricingModel: string
{
    case FijoMensual = 'fijo_mensual';
    case PorHora = 'por_hora';
    case PorGb = 'por_gb';

    /**
     * Etiqueta legible para la interfaz de usuario.
     */
    public function label(): string
    {
        return match ($this) {
            self::FijoMensual => 'Fijo mensual',
            self::PorHora => 'Por hora de uso',
            self::PorGb => 'Por GB transferido/almacenado',
        };
    }
}

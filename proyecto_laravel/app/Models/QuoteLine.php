<?php

namespace App\Models;

use Database\Factories\QuoteLineFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Línea de presupuesto con datos congelados (nombre, tipo, tarifa, config).
 * No cambia si el catálogo se modifica después de emitir el presupuesto.
 *
 * @property int $id
 * @property int $quote_id
 * @property string $product_name
 * @property string $product_type
 * @property string $pricing_model
 * @property int $quantity
 * @property int $unit_price_cents
 * @property int $line_total_cents
 * @property array|null $config
 */
class QuoteLine extends Model
{
    /** @use HasFactory<QuoteLineFactory> */
    use HasFactory;

    /**
     * Todos los campos se calculan en el servicio de emisión; ninguno
     * es asignable en masa para garantizar la inmutabilidad del snapshot.
     *
     * @var array<int, string>
     */
    protected $fillable = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'config' => 'array',
        ];
    }

    /**
     * Presupuesto al que pertenece esta línea.
     */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }
}

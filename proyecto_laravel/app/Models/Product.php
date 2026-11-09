<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Producto del catálogo (contrato de integración EP03/RF12).
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string $type
 * @property float $unit_price
 * @property bool $active
 */
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    /**
     * Tipos de producto contemplados por RF12. Solo el catálogo guarda
     * los atributos específicos de cada tipo (RF12/RN10); este listado
     * permite validar las etiquetas del contrato.
     *
     * @var list<string>
     */
    public const TYPES = [
        'computacion',
        'almacenamiento',
        'base_datos',
        'red',
        'seguridad',
        'backup',
        'monitorizacion',
        'computacion_ia',
        'servicio_ia',
        'soporte',
    ];

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'code',
        'name',
        'type',
        'unit_price',
        'active',
        'provider_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'active' => 'boolean',
        ];
    }

    public function regions(): BelongsToMany
    {
        return $this->belongsToMany(Region::class, 'product_availabilities')
            ->withPivot('provider_id', 'available', 'price_cents', 'pricing_model')
            ->withTimestamps();
    }

    /**
     * Proveedor del producto.
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    /**
     * Productos activos con disponibilidad declarada en `product_availabilities`.
     *
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopeAvailableIn(Builder $query, Region $region): Builder
    {
        return $query->where('active', true)
            ->whereHas('regions', fn ($relation) => $relation->where('regions.id', $region->id));
    }
}

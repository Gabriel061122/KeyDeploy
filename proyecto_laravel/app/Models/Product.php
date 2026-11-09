<?php

namespace App\Models;

use App\Enums\ProductType;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Producto / Servicio del catálogo Cloud (sección 5.1 UT2).
 *
 * @property int $id
 * @property int $provider_id
 * @property string $name
 * @property string|null $description
 * @property ProductType|string $type
 * @property string $billing_unit
 * @property array<string, mixed> $config
 * @property bool $active
 */
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'provider_id',
        'name',
        'description',
        'type',
        'billing_unit',
        'config',
        'active',
        'provider_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ProductType::class,
            'config' => 'array',
            'active' => 'boolean',
        ];
    }

    /**
     * Proveedor del producto (1:N con política restrict).
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    /**
     * Disponibilidades de este producto en distintas regiones (1:N con cascada en BD).
     */
    public function availabilities(): HasMany
    {
        return $this->hasMany(ProductAvailability::class);
    }

    /**
     * Regiones en las que se oferta el producto (N:M con datos en pivote).
     */
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
    public function scopeAvailableIn(Builder $query, Region|int $region): Builder
    {
        $regionId = $region instanceof Region ? $region->id : (int) $region;

        return $query->where('active', true)
            ->whereHas('availabilities', function ($q) use ($regionId) {
                $q->where('region_id', $regionId)->where('available', true);
            });
    }

    /**
     * Obtiene el precio en céntimos para una región dada.
     */
    public function priceCentsInRegion(Region|int $region): ?int
    {
        $regionId = $region instanceof Region ? $region->id : (int) $region;

        $availability = $this->relationLoaded('availabilities')
            ? $this->availabilities->firstWhere('region_id', $regionId)
            : $this->availabilities()->where('region_id', $regionId)->first();

        return $availability?->price_cents;
    }

    /**
     * Obtiene el precio en euros (decimal) para una región dada.
     */
    public function priceInRegion(Region|int $region): ?float
    {
        $cents = $this->priceCentsInRegion($region);

        return $cents !== null ? ($cents / 100) : null;
    }

    /**
     * Identificador amigable en slug del producto.
     */
    public function getCodeAttribute(): string
    {
        return Str::slug($this->name);
    }
}

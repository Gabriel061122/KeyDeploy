<?php

namespace App\Models;

use App\Enums\PricingModel;
use Database\Factories\ProductAvailabilityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Disponibilidad y tarificación de un producto en una región geográfica (sección 5.1 UT2).
 *
 * @property int $id
 * @property int $product_id
 * @property int $region_id
 * @property bool $available
 * @property int $price_cents
 * @property int $one_time_price_cents
 * @property PricingModel|string $pricing_model
 */
class ProductAvailability extends Model
{
    /** @use HasFactory<ProductAvailabilityFactory> */
    use HasFactory;

    protected $table = 'product_availabilities';

    protected $fillable = [
        'product_id',
        'region_id',
        'available',
        'price_cents',
        'one_time_price_cents',
        'pricing_model',
    ];

    protected function casts(): array
    {
        return [
            'available' => 'boolean',
            'price_cents' => 'integer',
            'one_time_price_cents' => 'integer',
            'pricing_model' => PricingModel::class,
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    /**
     * Accesor para obtener el precio en euros decimal.
     */
    public function getPriceEurosAttribute(): float
    {
        return $this->price_cents / 100;
    }

    /**
     * Accesor para obtener el cargo único en euros decimal.
     */
    public function getOneTimePriceEurosAttribute(): float
    {
        return $this->one_time_price_cents / 100;
    }
}

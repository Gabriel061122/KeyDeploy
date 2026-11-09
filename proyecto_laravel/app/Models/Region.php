<?php

namespace App\Models;

use Database\Factories\RegionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Región geográfica Cloud del catálogo (sección 5.1 UT2).
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string $country
 * @property bool $is_eu
 */
class Region extends Model
{
    /** @use HasFactory<RegionFactory> */
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'country',
        'is_eu',
    ];

    protected function casts(): array
    {
        return [
            'is_eu' => 'boolean',
        ];
    }

    /**
     * Disponibilidades directas registradas en esta región.
     */
    public function availabilities(): HasMany
    {
        return $this->hasMany(ProductAvailability::class);
    }

    /**
     * Productos asociados a la región mediante product_availabilities (N:M con datos).
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_availabilities')
            ->withPivot(['available', 'price_cents', 'pricing_model'])
            ->withTimestamps();
    }

    /**
     * Proyectos asignados a esta región.
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /**
     * Arquitecturas desplegadas en esta región.
     */
    public function architectures(): HasMany
    {
        return $this->hasMany(Architecture::class);
    }
}

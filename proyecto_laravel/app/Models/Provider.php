<?php

namespace App\Models;

use Database\Factories\ProviderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Proveedor de servicios Cloud (sección 5.1 UT2).
 *
 * @property int $id
 * @property string $name
 */
class Provider extends Model
{
    /** @use HasFactory<ProviderFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    /**
     * Productos que ofrece este proveedor (1:N).
     * Política de borrado restrict: no se puede borrar un proveedor con productos.
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}

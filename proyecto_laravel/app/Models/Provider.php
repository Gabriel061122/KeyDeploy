<?php

namespace App\Models;

use Database\Factories\ProviderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Proveedor de servicios cloud (NimbusCloud, Atlantic Hosting).
 *
 * @property int $id
 * @property string $name
 */
class Provider extends Model
{
    /** @use HasFactory<ProviderFactory> */
    use HasFactory;

    /**
     * @var array<int, string>
     */
    protected $fillable = ['name'];

    /**
     * Productos suministrados por este proveedor.
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}

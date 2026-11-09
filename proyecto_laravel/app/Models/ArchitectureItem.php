<?php

namespace App\Models;

use Database\Factories\ArchitectureItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Elemento de una arquitectura: producto del catálogo, cantidad entera
 * y configuración JSON individual gestionada con el cast de Eloquent.
 *
 * @property int $id
 * @property int $architecture_id
 * @property int $product_id
 * @property int $quantity
 * @property array<string, mixed> $config
 */
class ArchitectureItem extends Model
{
    /** @use HasFactory<ArchitectureItemFactory> */
    use HasFactory;

    /**
     * `architecture_id` se resuelve desde la arquitectura padre, no desde
     * la entrada del usuario.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'architecture_id',
        'product_id',
        'quantity',
        'config',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'config' => 'json',
        ];
    }

    public function architecture(): BelongsTo
    {
        return $this->belongsTo(Architecture::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}

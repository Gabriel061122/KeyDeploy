<?php

namespace App\Models;

use Database\Factories\CartItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Elemento del carrito de compra persistente por usuario.
 *
 * @property int $id
 * @property int $user_id
 * @property int $product_id
 * @property int $quantity
 * @property array|null $config
 */
class CartItem extends Model
{
    /** @use HasFactory<CartItemFactory> */
    use HasFactory;

    /**
     * `user_id` nunca es asignable en masa: se fija desde el usuario
     * autenticado en el controlador (RN09).
     *
     * @var array<int, string>
     */
    protected $fillable = [
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
            'config' => 'array',
        ];
    }

    /**
     * Usuario propietario del carrito.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Producto asociado a esta línea del carrito.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}

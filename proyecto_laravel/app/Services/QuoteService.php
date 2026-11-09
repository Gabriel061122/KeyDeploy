<?php

namespace App\Services;

use App\Models\CartItem;
use App\Models\Product;
use App\Models\Project;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class QuoteService
{
    /**
     * Emitir un presupuesto desde el carrito del usuario, congelando
     * nombre, tipo, tarifa y config dentro de una transacción atómica.
     *
     * @throws \Exception Si el carrito está vacío o la transacción falla.
     */
    public function emitFromCart(User $user, Project $project): Quote
    {
        $cartItems = CartItem::where('user_id', $user->id)
            ->with('product')
            ->get();

        if ($cartItems->isEmpty()) {
            throw new \Exception('No se puede emitir presupuesto: el carrito está vacío.');
        }

        // Validar reglas básicas
        $this->validateCartRules($cartItems, $project);

        $quote = DB::transaction(function () use ($user, $project, $cartItems) {
            // Calcular totales
            $subtotalMonthly = 0;
            $subtotalOneTime = 0;

            foreach ($cartItems as $item) {
                $product = $item->product;
                $pricingModel = $this->resolvePricingModel($product, $item);
                $unitPriceCents = $this->resolveUnitPriceCents($product, $item);
                $lineTotal = $unitPriceCents * $item->quantity;

                if ($pricingModel === 'mensual') {
                    $subtotalMonthly += $lineTotal;
                } else {
                    $subtotalOneTime += $lineTotal;
                }
            }

            $totalCents = $subtotalMonthly + $subtotalOneTime;

            // Crear cabecera del presupuesto
            $quote = Quote::forceCreate([
                'project_id' => $project->id,
                'user_id' => $user->id,
                'status' => 'emitido',
                'subtotal_monthly_cents' => $subtotalMonthly,
                'subtotal_one_time_cents' => $subtotalOneTime,
                'total_cents' => $totalCents,
                'issued_at' => now(),
            ]);

            // Crear líneas congeladas
            foreach ($cartItems as $item) {
                $product = $item->product;
                $pricingModel = $this->resolvePricingModel($product, $item);
                $unitPriceCents = $this->resolveUnitPriceCents($product, $item);
                $lineTotal = $unitPriceCents * $item->quantity;

                QuoteLine::forceCreate([
                    'quote_id' => $quote->id,
                    'product_name' => $product->name,
                    'product_type' => $product->type instanceof \BackedEnum ? $product->type->value : (string) $product->type,
                    'pricing_model' => $pricingModel,
                    'quantity' => $item->quantity,
                    'unit_price_cents' => $unitPriceCents,
                    'line_total_cents' => $lineTotal,
                    'config' => $item->config,
                ]);
            }

            // Vaciar el carrito
            CartItem::where('user_id', $user->id)->delete();

            return $quote;
        });

        return $quote;
    }

    /**
     * Validar las reglas de negocio del carrito antes de emitir.
     */
    protected function validateCartRules(Collection $cartItems, Project $project): void
    {
        // Mínimo 3 elementos
        if ($cartItems->count() < 3) {
            throw new \Exception('Se requieren al menos 3 productos para emitir un presupuesto.');
        }

        // Mínimo 2 tipos distintos
        $types = $cartItems->map(fn ($item) => $item->product->type instanceof \BackedEnum ? $item->product->type->value : (string) $item->product->type)->unique()->count();
        if ($types < 2) {
            throw new \Exception('Se requieren al menos 2 tipos de producto distintos.');
        }
    }

    /**
     * Resolver el modelo de tarifa para un producto.
     */
    protected function resolvePricingModel(Product $product, CartItem $item): string
    {
        // Intentar obtener de la disponibilidad en la región del proyecto
        $pivot = $product->regions()
            ->wherePivot('region_id', $item->config['region_id'] ?? null)
            ->first()?->pivot;

        if ($pivot && isset($pivot->pricing_model)) {
            return $pivot->pricing_model;
        }

        // Default según tipo
        $type = $product->type instanceof \BackedEnum ? $product->type->value : (string) $product->type;

        return match ($type) {
            'soporte' => 'por_uso',
            'backup' => 'unico',
            default => 'mensual',
        };
    }

    /**
     * Resolver el precio unitario en céntimos para un producto.
     */
    protected function resolveUnitPriceCents(Product $product, CartItem $item): int
    {
        // Intentar obtener de la disponibilidad en la región
        $pivot = $product->regions()
            ->wherePivot('region_id', $item->config['region_id'] ?? null)
            ->first()?->pivot;

        if ($pivot && isset($pivot->price_cents) && $pivot->price_cents > 0) {
            return (int) $pivot->price_cents;
        }

        // Fallback al unit_price del producto
        return (int) round($product->unit_price * 100);
    }
}

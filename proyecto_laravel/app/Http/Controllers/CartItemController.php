<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCartItemRequest;
use App\Http\Requests\UpdateCartItemRequest;
use App\Models\CartItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CartItemController extends Controller
{
    /**
     * Mostrar el carrito del usuario autenticado.
     */
    public function index(): View
    {
        $items = auth()->user()->cartItems()
            ->with('product')
            ->get();

        $totalCents = $items->sum(fn ($item) => $item->product->unit_price * 100 * $item->quantity);

        return view('cart.index', compact('items', 'totalCents'));
    }

    /**
     * Añadir un producto al carrito.
     */
    public function store(StoreCartItemRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $userId = auth()->id();
        $productId = $validated['product_id'];

        // Buscar si ya existe el item para este usuario y producto
        $item = CartItem::where('user_id', $userId)
            ->where('product_id', $productId)
            ->first();

        if ($item) {
            $item->update([
                'quantity' => $validated['quantity'] ?? 1,
                'config' => $validated['config'] ?? [],
            ]);
        } else {
            CartItem::forceCreate([
                'user_id' => $userId,
                'product_id' => $productId,
                'quantity' => $validated['quantity'] ?? 1,
                'config' => $validated['config'] ?? [],
            ]);
        }

        return redirect()->route('cart.index')
            ->with('success', 'Producto añadido al carrito.');
    }

    /**
     * Actualizar la cantidad de un elemento del carrito.
     */
    public function update(UpdateCartItemRequest $request, CartItem $item): RedirectResponse
    {
        // Autorización: solo el propietario
        abort_unless($item->user_id === auth()->id(), 403);

        $validated = $request->validated();
        $item->update($validated);

        return redirect()->route('cart.index')
            ->with('success', 'Cantidad actualizada.');
    }

    /**
     * Eliminar un elemento del carrito.
     */
    public function destroy(CartItem $item): RedirectResponse
    {
        abort_unless($item->user_id === auth()->id(), 403);

        $item->delete();

        return redirect()->route('cart.index')
            ->with('success', 'Producto eliminado del carrito.');
    }
}

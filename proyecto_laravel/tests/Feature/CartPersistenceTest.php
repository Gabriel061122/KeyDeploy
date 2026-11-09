<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Product;
use App\Models\Provider;
use App\Models\Region;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T05 - T07: Carrito persistente en base de datos.
 *
 * T05: Alta válida que persiste en BD y sobrevive al recargar.
 * T06: Alta inválida que NO persiste (producto inexistente o cantidad inválida).
 * T07: CRUD completo del carrito con relaciones Eloquent.
 */
class CartPersistenceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * T05: Añadir un producto válido al carrito lo persiste en la base
     * de datos. Al recargar la página, el elemento sigue presente.
     */
    public function test_valid_add_persists_to_database(): void
    {
        $provider = Provider::create(['name' => 'TestProvider']);
        $region = Region::create(['code' => 'cart-test', 'name' => 'Región carrito']);
        $user = User::factory()->create();

        $product = Product::create([
            'code' => 'cart-product',
            'name' => 'Producto del carrito',
            'type' => 'computacion',
            'unit_price' => 32.00,
            'provider_id' => $provider->id,
            'active' => true,
        ]);

        // Añadir producto al carrito
        $response = $this->actingAs($user)
            ->post(route('cart.store'), [
                'product_id' => $product->id,
                'quantity' => 2,
                'config' => ['region' => 'eu-west-1'],
            ]);

        $response->assertRedirect(route('cart.index'));

        // Verificar que persiste en BD
        $this->assertDatabaseCount('cart_items', 1);
        $this->assertDatabaseHas('cart_items', [
            'user_id' => $user->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        // Simular recargar la página del carrito
        $this->actingAs($user)
            ->get(route('cart.index'))
            ->assertOk()
            ->assertSee('Producto del carrito', false);
    }

    /**
     * T06: Intentar añadir un producto inválido al carrito NO persiste
     * el dato en la base de datos.
     */
    public function test_invalid_add_does_not_persist(): void
    {
        $user = User::factory()->create();

        // Intentar añadir un producto que no existe
        $this->actingAs($user)
            ->post(route('cart.store'), [
                'product_id' => 99999,
                'quantity' => 1,
            ])
            ->assertSessionHasErrors(['product_id' => 'El producto seleccionado no existe.']);

        // No se creó ningún elemento
        $this->assertDatabaseCount('cart_items', 0);

        // Intentar añadir con cantidad inválida (0)
        $this->actingAs($user)
            ->post(route('cart.store'), [
                'product_id' => 1,
                'quantity' => 0,
            ])
            ->assertSessionHasErrors(['quantity' => 'La cantidad debe ser al menos 1.']);

        // Todavía no hay elementos
        $this->assertDatabaseCount('cart_items', 0);

        // Intentar añadir sin product_id
        $this->actingAs($user)
            ->post(route('cart.store'), [
                'quantity' => 1,
            ])
            ->assertSessionHasErrors(['product_id' => 'Debes seleccionar un producto.']);

        $this->assertDatabaseCount('cart_items', 0);
    }

    /**
     * T07: CRUD completo del carrito con relaciones Eloquent.
     * Verificar que las relaciones user->cartItems y cartItem->product
     * funcionan con datos reales.
     */
    public function test_cart_crud_with_eloquent_relations(): void
    {
        $provider = Provider::create(['name' => 'TestProvider']);
        $region = Region::create(['code' => 'crud-test', 'name' => 'Región CRUD']);
        $user = User::factory()->create();

        $product1 = Product::create([
            'code' => 'crud-comp',
            'name' => 'Computación CRUD',
            'type' => 'computacion',
            'unit_price' => 32.00,
            'provider_id' => $provider->id,
            'active' => true,
        ]);

        $product2 = Product::create([
            'code' => 'crud-stor',
            'name' => 'Almacenamiento CRUD',
            'type' => 'almacenamiento',
            'unit_price' => 9.90,
            'provider_id' => $provider->id,
            'active' => true,
        ]);

        // CREATE: Añadir dos productos
        $this->actingAs($user)
            ->post(route('cart.store'), [
                'product_id' => $product1->id,
                'quantity' => 1,
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->post(route('cart.store'), [
                'product_id' => $product2->id,
                'quantity' => 3,
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('cart_items', 2);

        // READ: Verificar relación user->cartItems()->with('product')
        $items = $user->cartItems()->with('product')->get();
        $this->assertCount(2, $items);

        $item1 = $items->where('product_id', $product1->id)->first();
        $this->assertNotNull($item1);
        $this->assertSame('Computación CRUD', $item1->product->name);
        $this->assertSame('computacion', $item1->product->type);

        // UPDATE: Modificar cantidad
        $this->actingAs($user)
            ->patch(route('cart.update', $item1), [
                'quantity' => 5,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('cart_items', [
            'id' => $item1->id,
            'quantity' => 5,
        ]);

        // Verificar que otro usuario no puede modificar
        $otherUser = User::factory()->create();
        $this->actingAs($otherUser)
            ->patch(route('cart.update', $item1), [
                'quantity' => 10,
            ])
            ->assertForbidden();

        // DELETE: Eliminar un elemento
        $item2 = $items->where('product_id', $product2->id)->first();
        $this->actingAs($user)
            ->delete(route('cart.destroy', $item2))
            ->assertRedirect();

        $this->assertDatabaseMissing('cart_items', ['id' => $item2->id]);
        $this->assertDatabaseCount('cart_items', 1);

        // Verificar que otro usuario no puede eliminar
        $this->actingAs($otherUser)
            ->delete(route('cart.destroy', $item1))
            ->assertForbidden();

        $this->assertDatabaseCount('cart_items', 1);
    }
}

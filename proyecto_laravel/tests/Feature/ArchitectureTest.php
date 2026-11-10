<?php

namespace Tests\Feature;

use App\Models\Architecture;
use App\Models\ArchitectureItem;
use App\Models\Product;
use App\Models\Project;
use App\Models\Region;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesCatalogData;
use Tests\TestCase;

/**
 * Creación manual de arquitecturas (RF05/RN05/RN08): mínimo de 3
 * elementos y 2 tipos de producto distintos, existencia y estado activo
 * de los productos, disponibilidad real por región y guardado atómico
 * (transacción: nunca arquitecturas parciales a medio guardar).
 */
class ArchitectureTest extends TestCase
{
    use CreatesCatalogData, RefreshDatabase;

    private function projectFor(User $user, ?Region $region = null): Project
    {
        return Project::factory()->create([
            'user_id' => $user->id,
            'region_id' => ($region ?? $this->createRegion())->id,
        ]);
    }

    private function payload(Project $project, array $items, ?int $regionId = null): array
    {
        return [
            'name' => 'Arquitectura de pruebas',
            'region_id' => $regionId ?? $project->region_id,
            'items' => $items,
        ];
    }

    /**
     * Flujo completo sin JavaScript: formulario → revisión
     * (borrador en sesión) → guardado atómico en la base de datos.
     */
    public function test_owner_creates_a_manual_architecture_in_three_steps(): void
    {
        $user = User::factory()->create();
        $region = $this->createRegion();
        $project = $this->projectFor($user, $region);
        $items = $this->validItemsFor($region);

        $this->actingAs($user)
            ->get(route('projects.architectures.create', $project))
            ->assertOk();

        $this->post(route('projects.architectures.review', $project), $this->payload($project, $items))
            ->assertRedirect(route('projects.architectures.check', $project));

        $firstProduct = Product::query()->findOrFail($items[0]['product_id']);
        $this->get(route('projects.architectures.check', $project))
            ->assertOk()
            ->assertSee('Revisar arquitectura antes de guardar')
            ->assertSee('Confirmar y guardar arquitectura')
            ->assertSee($firstProduct->code, false);

        $response = $this->post(route('projects.architectures.store', $project));

        $architecture = Architecture::query()->firstOrFail();
        $response->assertRedirect(route('architectures.show', $architecture));

        $this->assertSame($project->id, $architecture->project_id);
        $this->assertSame($region->id, $architecture->region_id);
        $this->assertSame('manual', $architecture->source);
        $this->assertSame(3, $architecture->items()->count());

        // Cada elemento con su cantidad y su configuración JSON decodificada.
        foreach ($items as $item) {
            $stored = $architecture->items()->where('product_id', $item['product_id'])->firstOrFail();
            $this->assertSame($item['quantity'], $stored->quantity);
            $this->assertSame(json_decode($item['config'], true), $stored->config);
        }

        // El borrador se consume al guardar: no queda revisión pendiente.
        $this->get(route('projects.architectures.check', $project))->assertRedirect();
    }

    /**
     * El formulario lista únicamente productos activos y disponibles en
     * la región elegida (RN05), recargando con GET sin JavaScript.
     */
    public function test_create_page_lists_only_products_available_in_the_selected_region(): void
    {
        $user = User::factory()->create();
        $regionA = $this->createRegion('region-alpha');
        $regionB = $this->createRegion('region-beta');
        $project = $this->projectFor($user, $regionA);

        $availableEverywhere = $this->createProduct($this->uniqueCode('global'), 'red', [$regionA->id, $regionB->id]);
        $onlyInB = $this->createProduct($this->uniqueCode('sparse'), 'seguridad', [$regionB->id]);

        $this->actingAs($user)
            ->get(route('projects.architectures.create', ['project' => $project, 'region_id' => $regionA->id]))
            ->assertOk()
            ->assertSee($availableEverywhere->code, false)
            ->assertDontSee($onlyInB->code, false);

        $this->get(route('projects.architectures.create', ['project' => $project, 'region_id' => $regionB->id]))
            ->assertOk()
            ->assertSee($onlyInB->code, false)
            ->assertSee($availableEverywhere->code, false);
    }

    /**
     * Menos de 3 elementos: rechazo íntegro y sin guardado parcial.
     */
    public function test_rejects_architectures_with_fewer_than_three_elements(): void
    {
        $user = User::factory()->create();
        $region = $this->createRegion();
        $project = $this->projectFor($user, $region);
        $items = $this->validItemsFor($region);

        $this->actingAs($user)
            ->post(route('projects.architectures.review', $project), $this->payload($project, array_slice($items, 0, 2)))
            ->assertSessionHasErrors(['items']);

        $this->assertDatabaseCount('architectures', 0);
        $this->assertDatabaseCount('architecture_items', 0);
    }

    /**
     * Un único tipo de producto repetido no compone una arquitectura:
     * se exigen al menos 2 tipos distintos.
     */
    public function test_rejects_architectures_with_a_single_product_type(): void
    {
        $user = User::factory()->create();
        $region = $this->createRegion();
        $project = $this->projectFor($user, $region);
        $compute = $this->createProduct($this->uniqueCode('comp'), 'computacion', [$region->id]);

        $items = [
            ['product_id' => $compute->id, 'quantity' => 1, 'config' => '{"rol": "web"}'],
            ['product_id' => $compute->id, 'quantity' => 2, 'config' => '{"rol": "cola"}'],
            ['product_id' => $compute->id, 'quantity' => 1, 'config' => '{"rol": "trabajos"}'],
        ];

        $this->actingAs($user)
            ->post(route('projects.architectures.review', $project), $this->payload($project, $items))
            ->assertSessionHasErrors(['items']);

        $this->assertDatabaseCount('architectures', 0);
    }

    /**
     * Productos inexistentes en el catálogo no se contratan.
     */
    public function test_rejects_nonexistent_products(): void
    {
        $user = User::factory()->create();
        $region = $this->createRegion();
        $project = $this->projectFor($user, $region);
        $items = $this->validItemsFor($region);
        $items[] = ['product_id' => 999999, 'quantity' => 1, 'config' => '{}'];

        $this->actingAs($user)
            ->post(route('projects.architectures.review', $project), $this->payload($project, $items))
            ->assertSessionHasErrors(['items.3.product_id']);

        $this->assertDatabaseCount('architectures', 0);
    }

    /**
     * Productos retirados (inactivos) no se contratan aunque sigan
     * declarados disponibles: el listado no los ofrece y el servidor
     * rechaza un envío manual con ese identificador.
     */
    public function test_rejects_inactive_products(): void
    {
        $user = User::factory()->create();
        $region = $this->createRegion();
        $project = $this->projectFor($user, $region);
        $items = $this->validItemsFor($region);
        $retired = $this->createProduct($this->uniqueCode('old'), 'monitorizacion', [$region->id], active: false);

        $items[2] = ['product_id' => $retired->id, 'quantity' => 1, 'config' => '{}'];

        $this->actingAs($user)
            ->post(route('projects.architectures.review', $project), $this->payload($project, $items))
            // Tres elementos y dos tipos: el rechazo se debe al estado activo.
            ->assertSessionHasErrors(['items']);

        $this->assertDatabaseCount('architectures', 0);
    }

    /**
     * RN05: un producto que no está disponible en la región exigida
     * nunca puede contratarse, aunque exista y esté activo.
     */
    public function test_rejects_products_not_available_in_the_selected_region(): void
    {
        $user = User::factory()->create();
        $regionA = $this->createRegion('region-a');
        $regionB = $this->createRegion('region-b');
        $project = $this->projectFor($user, $regionA);
        $items = $this->validItemsFor($regionA);
        $exclusive = $this->createProduct($this->uniqueCode('onlyb'), 'backup', [$regionB->id]);

        $items[2] = ['product_id' => $exclusive->id, 'quantity' => 1, 'config' => '{}'];

        $this->actingAs($user)
            ->post(route('projects.architectures.review', $project), $this->payload($project, $items))
            ->assertSessionHasErrors(['items']);

        $this->assertDatabaseCount('architectures', 0);
    }

    /**
     * Cantidades menores que 1 (0, negativas o no enteras) no se
     * aceptan.
     */
    #[DataProvider('invalid_quantity_cases')]
    public function test_rejects_non_positive_or_fractional_quantities(string|int $quantity): void
    {
        $user = User::factory()->create();
        $region = $this->createRegion();
        $project = $this->projectFor($user, $region);
        $items = $this->validItemsFor($region);
        $items[0]['quantity'] = $quantity;

        $this->actingAs($user)
            ->post(route('projects.architectures.review', $project), $this->payload($project, $items))
            ->assertSessionHasErrors(['items.0.quantity']);

        $this->assertDatabaseCount('architectures', 0);
        $this->assertDatabaseCount('architecture_items', 0);
    }

    public static function invalid_quantity_cases(): array
    {
        return [
            'zero' => [0],
            'negative' => [-1],
            'fractional' => ['1.5'],
        ];
    }

    /**
     * Cada elemento necesita una configuración JSON individual válida
     * (se admite un objeto vacío {}).
     */
    public function test_rejects_invalid_json_configuration(): void
    {
        $user = User::factory()->create();
        $region = $this->createRegion();
        $project = $this->projectFor($user, $region);
        $items = $this->validItemsFor($region);
        $items[1]['config'] = '{"instancia": "t3.medium"'; // JSON malformado

        $this->actingAs($user)
            ->post(route('projects.architectures.review', $project), $this->payload($project, $items))
            ->assertSessionHasErrors(['items']);

        $this->assertDatabaseCount('architectures', 0);
        $this->assertDatabaseCount('architecture_items', 0);
    }

    /**
     * Nombre o región ausentes bloquean la revisión.
     */
    public function test_missing_name_or_region_blocks_review(): void
    {
        $user = User::factory()->create();
        $region = $this->createRegion();
        $project = $this->projectFor($user, $region);
        $items = $this->validItemsFor($region);

        $base = ['items' => $items];

        $this->actingAs($user)
            ->post(route('projects.architectures.review', $project), $base + ['name' => '', 'region_id' => $project->region_id])
            ->assertSessionHasErrors(['name']);

        $this->actingAs($user)
            ->post(route('projects.architectures.review', $project), $base + [
                'name' => 'Sin región',
                'region_id' => 999999,
            ])->assertSessionHasErrors(['region_id']);

        $this->assertDatabaseCount('architectures', 0);
    }

    /**
     * Solo el propietario del proyecto puede crear arquitecturas en él
     * (RF17/RN09): 403 en el formulario y en la revisión.
     */
    public function test_foreign_projects_reject_architecture_creation(): void
    {
        $user = User::factory()->create();
        $region = $this->createRegion();
        $project = $this->projectFor($user, $region);
        $items = $this->validItemsFor($region);

        $this->actingAs($this->anotherCliente())
            ->get(route('projects.architectures.create', $project))
            ->assertForbidden();

        $this->post(route('projects.architectures.review', $project), $this->payload($project, $items))
            ->assertForbidden();

        $this->assertDatabaseCount('architectures', 0);
        $this->assertDatabaseCount('architecture_items', 0);
    }

    /**
     * La previsualización y el guardado exigen un borrador pendiente en
     * la sesión para ese proyecto.
     */
    public function test_check_and_store_require_a_pending_draft(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);

        $this->actingAs($user)
            ->get(route('projects.architectures.check', $project))
            ->assertRedirect();

        $this->post(route('projects.architectures.store', $project))
            ->assertRedirect();

        $this->assertDatabaseCount('architectures', 0);
        $this->assertDatabaseCount('architecture_items', 0);
    }

    /**
     * Un borrador validado para el proyecto A no puede usarse para
     * guardar en el proyecto B de la misma cuenta.
     */
    public function test_a_pending_draft_cannot_be_used_for_a_different_project(): void
    {
        $user = User::factory()->create();
        $region = $this->createRegion();
        $projectA = $this->projectFor($user, $region);
        $projectB = $this->projectFor($user, $region);
        $items = $this->validItemsFor($region);

        $this->actingAs($user)
            ->post(route('projects.architectures.review', $projectA), $this->payload($projectA, $items))
            ->assertRedirect(route('projects.architectures.check', $projectA));

        $this->post(route('projects.architectures.store', $projectB))
            ->assertRedirect(route('projects.architectures.create', $projectB));

        $this->assertDatabaseCount('architectures', 0);
        $this->assertDatabaseCount('architecture_items', 0);
    }

    /**
     * El detalle de una arquitectura solo lo ve el propietario del
     * proyecto (RF17/RN09).
     */
    public function test_architecture_detail_page_is_owner_only(): void
    {
        $user = User::factory()->create();
        $region = $this->createRegion();
        $project = $this->projectFor($user, $region);
        $product = $this->createProduct($this->uniqueCode('net'), 'red', [$region->id]);
        $architecture = Architecture::factory()->create([
            'project_id' => $project->id,
            'region_id' => $region->id,
            'name' => 'Arquitectura ajena',
        ]);
        ArchitectureItem::factory()->create([
            'architecture_id' => $architecture->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $this->actingAs($this->anotherCliente())
            ->get(route('architectures.show', $architecture))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('architectures.show', $architecture))
            ->assertOk()
            ->assertSee('Arquitectura ajena')
            ->assertSee($product->code, false)
            ->assertSee('2', false);
    }

    /**
     * Si ocurre un fallo al guardar un elemento, DB::transaction()
     * revierte toda la operación: nunca se guarda una arquitectura
     * incompleta o parcial (principio de coherencia RN08).
     */
    public function test_architecture_saving_is_atomic_and_rolls_back_if_an_item_fails(): void
    {
        $user = User::factory()->create();
        $region = $this->createRegion();
        $project = $this->projectFor($user, $region);
        $items = $this->validItemsFor($region);

        $this->actingAs($user)
            ->post(route('projects.architectures.review', $project), $this->payload($project, $items))
            ->assertRedirect(route('projects.architectures.check', $project));

        ArchitectureItem::saving(function () {
            throw new \RuntimeException('Fallo simulado al persistir un elemento.');
        });

        try {
            $this->post(route('projects.architectures.store', $project));
        } catch (\RuntimeException $e) {
            $this->assertSame('Fallo simulado al persistir un elemento.', $e->getMessage());
        }

        // La transacción se revirtió por completo: 0 arquitecturas y 0 elementos guardados.
        $this->assertDatabaseCount('architectures', 0);
        $this->assertDatabaseCount('architecture_items', 0);
    }
}

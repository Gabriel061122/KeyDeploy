<?php

namespace Tests\Feature;

use App\Models\Architecture;
use App\Models\ArchitectureItem;
use App\Models\Product;
use App\Models\Project;
use App\Models\Region;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
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

        // Cada elemento con su cantidad; la configuración interna se
        // construye en servidor (tarifas fijas: vacía) y el navegador
        // no envía JSON.
        foreach ($items as $item) {
            $stored = $architecture->items()->where('product_id', $item['product_id'])->firstOrFail();
            $this->assertSame($item['quantity'], $stored->quantity);
            $this->assertSame([], $stored->config);
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
            ['product_id' => $compute->id, 'quantity' => 1],
            ['product_id' => $compute->id, 'quantity' => 2],
            ['product_id' => $compute->id, 'quantity' => 1],
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
        $items[] = ['product_id' => 999999, 'quantity' => 1];

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

        $items[2] = ['product_id' => $retired->id, 'quantity' => 1];

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

        $items[2] = ['product_id' => $exclusive->id, 'quantity' => 1];

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
     * Criterio 8: el cliente ya no envía configuración. Cualquier valor
     * `items[*][config]` de la petición se IGNORA: un JSON válido, uno
     * malformado o incluso un array no altera el resultado ni provoca
     * errores. La configuración la construye el servidor a partir del
     * consumo mensual.
     */
    public function test_config_sent_by_the_client_is_always_ignored(): void
    {
        $cases = [
            'json malformado' => '{"vcpu": "4"', // JSON malformado
            'json válido' => '{"vcpu": "99", "ram_gb": "999", "hours_per_month": "1"}',
            'array php' => ['vcpu' => 99],
        ];

        foreach ($cases as $context => $config) {
            $user = User::factory()->create();
            $region = $this->createRegion();
            $project = $this->projectFor($user, $region);
            $items = $this->validItemsFor($region);
            $items[1]['config'] = $config;

            $this->actingAs($user)
                ->post(route('projects.architectures.review', $project), $this->payload($project, $items))
                ->assertSessionHasNoErrors();

            $this->post(route('projects.architectures.store', $project));
            $architecture = Architecture::query()->where('project_id', $project->id)->firstOrFail();

            $stored = $architecture->items()->where('product_id', $items[1]['product_id'])->firstOrFail();
            $this->assertSame(
                [],
                $stored->config,
                'Caso "'.$context.'": el cliente no puede inyectar configuración.',
            );
        }
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

    /**
     * Criterio 1: el formulario de alta no contiene ningún campo ni
     * texto que mencione JSON o «configuración JSON»; en cambio, cada
     * uno de los 6 huecos ofrece su propio campo numérico de consumo
     * mensual (`items[*][usage]`).
     */
    public function test_create_form_offers_monthly_usage_instead_of_json_configuration(): void
    {
        $user = User::factory()->create();
        $region = $this->createRegion();
        $project = $this->projectFor($user, $region);
        $this->createProduct($this->uniqueCode('comp'), 'computacion', [$region->id]);

        $html = $this->actingAs($user)
            ->get(route('projects.architectures.create', ['project' => $project, 'region_id' => $region->id]))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsStringIgnoringCase('json', $html);
        $this->assertStringNotContainsStringIgnoringCase('configuración json', $html);

        for ($i = 0; $i < 6; $i++) {
            $this->assertStringContainsString(
                'name="items['.$i.'][usage]"',
                $html,
                'Falta el campo «Consumo mensual» del hueco '.($i + 1).'.',
            );
        }
    }

    /**
     * Criterio 2: con 3 elementos rellenos y 3 huecos totalmente
     * vacíos el alta es válida (> «al menos 3»), y los huecos vacíos
     * no generan errores.
     */
    public function test_three_filled_and_three_empty_slots_are_a_valid_architecture(): void
    {
        $user = User::factory()->create();
        $region = $this->createRegion();
        $project = $this->projectFor($user, $region);
        $items = $this->validItemsFor($region);

        $this->actingAs($user)
            ->post(route('projects.architectures.review', $project), $this->payload(
                $project,
                array_merge($items, $this->emptySlots(3)),
            ))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('projects.architectures.check', $project));

        $this->post(route('projects.architectures.store', $project));

        $architecture = Architecture::query()->where('project_id', $project->id)->firstOrFail();
        $this->assertSame(3, $architecture->items()->count());
    }

    /**
     * Criterio 3: el formulario completo (6 huecos rellenos) es válido.
     */
    public function test_all_six_slots_filled_is_valid(): void
    {
        $user = User::factory()->create();
        $region = $this->createRegion();
        $project = $this->projectFor($user, $region);

        $computers = collect(range(1, 3))->map(fn () => $this->createProduct($this->uniqueCode('comp'), 'computacion', [$region->id]));
        $storages = collect(range(1, 3))->map(fn () => $this->createProduct($this->uniqueCode('store'), 'almacenamiento', [$region->id]));

        $products = $computers->merge($storages);

        $items = $products
            ->map(fn ($product): array => ['product_id' => $product->id, 'quantity' => 1])
            ->values()
            ->all();

        $this->actingAs($user)
            ->post(route('projects.architectures.review', $project), $this->payload($project, $items))
            ->assertSessionHasNoErrors();

        $this->post(route('projects.architectures.store', $project));

        $architecture = Architecture::query()->where('project_id', $project->id)->firstOrFail();
        $this->assertSame(6, $architecture->items()->count());
    }

    /**
     * Criterio 4: una fila parcial (producto sin cantidad, o cantidad
     * sin producto) se rechaza y el error cita el número del hueco
     * visible; los huecos totalmente vacíos no generan errores.
     */
    #[DataProvider('partial_slot_shapes')]
    public function test_a_partially_filled_slot_is_rejected_citing_the_visible_slot(array $partial, array $complete): void
    {
        $user = User::factory()->create();
        $region = $this->createRegion();
        $project = $this->projectFor($user, $region);
        $items = $this->validItemsFor($region);

        $stray = $this->createProduct($this->uniqueCode('extra'), 'seguridad', [$region->id]);

        if (isset($partial['product_id'])) {
            $partial['product_id'] = $stray->id;
        }

        $items = array_merge($items, $this->emptySlots(2), [$partial + $complete]);

        $this->actingAs($user)
            ->post(route('projects.architectures.review', $project), $this->payload($project, $items))
            ->assertSessionHasErrors();

        $errors = $this->allSessionErrors();
        $this->assertNotEmpty($errors, 'La fila parcial debe producir mensajes de error.');

        $this->assertTrue(
            collect($errors)->contains(fn (string $message): bool => (bool) preg_match('/\b6\b/', $message)),
            'El error de la fila parcial debe citar el hueco visible (hueco 6). '
            .'Mensajes recibidos: ['.implode(' | ', $errors).']',
        );

        $this->assertDatabaseCount('architectures', 0);
        $this->assertDatabaseCount('architecture_items', 0);
    }

    public static function partial_slot_shapes(): array
    {
        return [
            'producto sin cantidad' => [['product_id' => 'no-vacío'], []],
            'cantidad sin producto' => [[], ['quantity' => '3']],
        ];
    }

    /**
     * Criterio 11: si la región no tiene productos activos y
     * disponibles, el formulario atenua el envío — muestra un aviso
     * claro y no ofrece el botón de revisión.
     */
    public function test_create_page_warns_about_a_region_without_products_and_offer_no_review(): void
    {
        $user = User::factory()->create();
        $emptyRegion = $this->createRegion();
        $project = $this->projectFor($user, $emptyRegion);

        $html = $this->actingAs($user)
            ->get(route('projects.architectures.create', ['project' => $project, 'region_id' => $emptyRegion->id]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsStringIgnoringCase(
            'productos',
            $html,
            'La página debe avisar de que la región no tiene productos disponibles.',
        );
        $this->assertStringNotContainsString(
            'Revisar y previsualizar',
            $html,
            'Sin productos, el formulario no debe ofrecer el botón de envío a revisión.',
        );
    }

    /**
     * Criterio 11: una petición directa a la revisión con una región
     * sin productos se rechaza con un mensaje comprensible; nada se
     * persiste ni queda borrador pendiente.
     */
    public function test_direct_revision_of_a_region_without_products_is_rejected_comprehensibly(): void
    {
        $user = User::factory()->create();
        $emptyRegion = $this->createRegion();
        $project = $this->projectFor($user, $emptyRegion);

        $otherRegion = $this->createRegion('con-catalogo');
        $items = $this->validItemsFor($otherRegion);

        $this->actingAs($user)
            ->post(route('projects.architectures.review', $project), $this->payload($project, $items, $emptyRegion->id))
            ->assertSessionHasErrors();

        $errors = $this->allSessionErrors();
        $this->assertNotEmpty($errors, 'La región sin productos debe rechazar la revisión con mensajes.');

        $this->assertTrue(
            collect($errors)->contains(fn (string $message): bool => str_contains($message, $emptyRegion->name)
                || stripos(mb_strtolower($message), 'no hay productos') !== false
                || stripos(mb_strtolower($message), 'sin productos') !== false),
            'El rechazo debe ser comprensible (región sin productos). Mensajes: ['
            .implode(' | ', $errors).']',
        );

        $this->assertDatabaseCount('architectures', 0);
        $this->assertDatabaseCount('architecture_items', 0);
        // Sin borrador pendiente para el proyecto.
        $this->get(route('projects.architectures.check', $project))->assertRedirect();
    }

    /**
     * Criterio 12: «Actualizar» región recarga el formulario con la
     * entrada antigua: conserva nombre, productos, cantidades y
     * consumo, muestra el catálogo de la región nueva y no guarda nada
     * ni deja borrador pendiente.
     */
    public function test_updating_the_region_conserves_typed_data_and_stores_nothing(): void
    {
        $user = User::factory()->create();
        $regionA = $this->createRegion('origen');
        $regionB = $this->createRegion('destino');
        $project = $this->projectFor($user, $regionA);

        // Catálago visible inicialmente en la región A.
        $aComp = $this->createProduct($this->uniqueCode('a-comp'), 'computacion', [$regionA->id]);
        $aStore = $this->createProduct($this->uniqueCode('a-store'), 'almacenamiento', [$regionA->id]);
        // Catálogo que solo existe en la región B.
        $bComp = $this->createProduct($this->uniqueCode('b-comp'), 'computacion', [$regionB->id]);
        $bStore = $this->createProduct($this->uniqueCode('b-store'), 'almacenamiento', [$regionB->id]);

        $this->actingAs($user)
            ->get(route('projects.architectures.create', ['project' => $project, 'region_id' => $regionA->id]))
            ->assertOk()
            ->assertSee($aComp->code, false);

        $reloadUrl = $this->regionReloadAction(
            route('projects.architectures.create', ['project' => $project, 'region_id' => $regionA->id]),
            route('projects.architectures.review', $project),
        );

        // POST de recarga con entrada antigua: nombre + 3 filas
        // (productos de A, cantidades y consumo).
        $this->actingAs($user)
            ->post($reloadUrl, [
                'name' => 'Arquitectura transferida',
                'region_id' => $regionB->id,
                'items' => [
                    ['product_id' => $aComp->id, 'quantity' => 2, 'usage' => '12.5'],
                    ['product_id' => $aStore->id, 'quantity' => 1, 'usage' => ''],
                    ['product_id' => $bComp->id, 'quantity' => 3, 'usage' => '7'],
                ],
            ])
            ->assertRedirect();

        // La recarga NO guarda nada ni crea borrador pendiente.
        $this->assertDatabaseCount('architectures', 0);
        $this->assertDatabaseCount('architecture_items', 0);

        // El formulario reaparece con la entrada antigua intacta y el
        // catálogo de la región nueva. Se lee antes de consumir la
        // entrada antigua con la comprobación del borrador.
        $html = $this->get(route('projects.architectures.create', ['project' => $project, 'region_id' => $regionB->id]))
            ->assertOk()
            ->getContent();

        $this->assertSame(
            'Arquitectura transferida',
            $this->inputValue($html, 'name'),
            'El nombre tecleado se conserva al recargar la región.',
        );
        $this->assertSame('2', $this->inputValue($html, 'items[0][quantity]'));
        $this->assertSame('1', $this->inputValue($html, 'items[1][quantity]'));
        $this->assertSame('3', $this->inputValue($html, 'items[2][quantity]'));
        $this->assertSame('12.5', $this->inputValue($html, 'items[0][usage]'));
        $this->assertSame('7', $this->inputValue($html, 'items[2][usage]'));

        $this->assertStringContainsString($bComp->code, $html, 'El catálogo debe ser el de la región elegida.');
        $this->assertStringNotContainsString($aStore->code, $html);

        // Y la recarga no deja borrador pendiente de revisión.
        $this->get(route('projects.architectures.check', $project))->assertRedirect();
    }

    /**
     * Valor del atributo `value` del primer input cuyo atributo `name`
     * coincide (independiente del orden de atributos del HTML).
     */
    private function inputValue(string $html, string $name): ?string
    {
        preg_match_all('/<input\b([^>]*)>/i', $html, $tags);

        foreach ($tags[1] as $attributes) {
            $fieldName = preg_match('/name\s*=\s*["\']([^"\']*)["\']/i', $attributes, $n) ? $n[1] : null;

            if ($fieldName !== $name) {
                continue;
            }

            return preg_match('/value\s*=\s*["\']([^"\']*)["\']/i', $attributes, $v)
                ? html_entity_decode($v[1], ENT_QUOTES | ENT_HTML5)
                : null;
        }

        return null;
    }

    /**
     * busca la acción de recarga de región que ofrece el formulario de
     * alta (criterio 12: formulario único + acción de recarga POST con
     * entrada antigua). Lo localiza por el atributo formaction de un
     * botón «Actualizar» o por un formulario POST distinto del envío a
     * revisión.
     */
    private function regionReloadAction(string $pageUrl, string $reviewUrl): string
    {
        $html = $this->get($pageUrl)->assertOk()->getContent();

        if (preg_match('/formaction\s*=\s*["\']([^"\']+)["\']/i', $html, $match)) {
            return html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5);
        }

        // Alternativa: un POST propio distinto del cierre de sesión y
        // del envío a revisión.
        preg_match_all('/<form\b([^>]*)>(.*?)<\/form>/si', $html, $candidates, PREG_SET_ORDER);

        foreach ($candidates as [$attributes, $inner]) {
            if (! preg_match('/method\s*=\s*["\']?post\b/i', $attributes)) {
                continue;
            }

            $action = preg_match('/action\s*=\s*["\']([^"\']*)["\']/i', $attributes, $m)
                ? html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5)
                : $pageUrl;

            if (! str_contains($action, 'salir') && $action !== $reviewUrl) {
                return $action;
            }
        }

        $this->fail(
            'Criterio 12: el formulario único debe ofrecer una acción de recarga '
            .'(botón «Actualizar» con formaction a una ruta POST, o formulario POST '
            .'propio distinto de la revisión). No se encontró.',
        );
    }

    /**
     * @return list<string>
     */
    private function allSessionErrors(): array
    {
        $bag = session('errors');

        if ($bag === null) {
            return [];
        }

        if ($bag instanceof ViewErrorBag) {
            return $bag->all();
        }

        if ($bag instanceof MessageBag) {
            return $bag->all();
        }

        if (is_array($bag)) {
            return collect($bag)->flatten()->all();
        }

        return [$bag];
    }
}

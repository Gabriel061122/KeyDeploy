<?php

namespace Tests\Feature;

use App\Models\Architecture;
use App\Models\ArchitectureItem;
use App\Models\Project;
use App\Models\Requirement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesCatalogData;
use Tests\TestCase;

/**
 * CRUD de proyectos (RF02) con borrado coherente (SoftDeletes),
 * restauración, protección del propietario y conservación de las
 * relaciones al eliminar (RF17/RN09).
 */
class ProjectTest extends TestCase
{
    use CreatesCatalogData, RefreshDatabase;

    public function test_creating_project_assigns_authenticated_owner(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $region = $this->createRegion();

        $response = $this->actingAs($user)->post(route('projects.store'), [
            'name' => 'Tienda online',
            'description' => 'Aplicación de venta online con pasarela simulada.',
            'region_id' => $region->id,
            'max_budget' => 1500.75,
            'expected_users' => 500,
            'user_id' => $other->id, // debe ignorarse: el servidor fija el dueño.
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('projects', [
            'name' => 'Tienda online',
            'user_id' => $user->id,
            'region_id' => $region->id,
            'max_budget' => 1500.75,
            'expected_users' => 500,
        ]);
    }

    public function test_missing_required_fields_blocks_saving(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('projects.store'), [])
            ->assertSessionHasErrors(['name', 'description', 'region_id', 'max_budget', 'expected_users']);

        $this->assertDatabaseCount('projects', 0);
    }

    public function test_invalid_values_blocks_saving(): void
    {
        $user = User::factory()->create();
        $this->createRegion();

        $this->actingAs($user)->post(route('projects.store'), [
            'name' => 'Presupuesto absurdo',
            'description' => 'Prueba de validaciones numéricas.',
            'region_id' => 999999,
            'max_budget' => -5,
            'expected_users' => 0,
        ])->assertSessionHasErrors(['region_id', 'max_budget', 'expected_users']);

        $this->assertDatabaseCount('projects', 0);
    }

    public function test_owner_edits_project(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $oldRegion = $this->createRegion('old-1');
        $newRegion = $this->createRegion('new-1');
        $project = Project::factory()->create([
            'user_id' => $user->id,
            'region_id' => $oldRegion->id,
        ]);

        $this->actingAs($user)
            ->put(route('projects.update', $project), [
                'name' => 'Tienda online 2.0',
                'description' => $project->description,
                'region_id' => $newRegion->id,
                'max_budget' => 2000,
                'expected_users' => 750,
                'user_id' => $other->id,
            ])
            ->assertRedirect(route('projects.show', $project));

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'name' => 'Tienda online 2.0',
            'region_id' => $newRegion->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_delete_is_logical_and_keeps_relations(): void
    {
        $user = User::factory()->create();
        $region = $this->createRegion();
        $project = Project::factory()->create([
            'user_id' => $user->id,
            'region_id' => $region->id,
        ]);
        // Relaciones que NO deben destruirse al borrar el proyecto.
        Requirement::factory()->create([
            'project_id' => $project->id,
            'field_key' => 'usuarios_simultaneos',
        ]);
        Architecture::factory()->create([
            'project_id' => $project->id,
            'region_id' => $region->id,
        ]);

        $response = $this->actingAs($user)->delete(route('projects.destroy', $project));

        $response->assertRedirect(route('projects.index'));
        $this->assertSoftDeleted('projects', ['id' => $project->id]);
        // Sin cascadas destructivas: requerimientos y arquitecturas se conservan.
        $this->assertDatabaseHas('requirements', ['project_id' => $project->id, 'field_key' => 'usuarios_simultaneos']);
        $this->assertDatabaseHas('architectures', ['project_id' => $project->id]);
    }

    public function test_deleted_project_hides_from_lists_and_restores_from_trash(): void
    {
        $user = User::factory()->create();
        $region = $this->createRegion();
        $project = Project::factory()->create([
            'user_id' => $user->id,
            'region_id' => $region->id,
            'name' => 'Proyecto archivado',
        ]);
        $requirement = Requirement::factory()->create([
            'project_id' => $project->id,
            'field_key' => 'usuarios_simultaneos',
        ]);
        $architecture = Architecture::factory()->create([
            'project_id' => $project->id,
            'region_id' => $region->id,
        ]);
        $product = $this->createProduct($this->uniqueCode('restore'), 'computacion', [$region->id]);
        $architectureItem = ArchitectureItem::factory()->create([
            'architecture_id' => $architecture->id,
            'product_id' => $product->id,
        ]);
        $project->delete();

        // El listado normal no lo muestra.
        $this->actingAs($user)->get(route('projects.index'))->assertOk()->assertDontSee('Proyecto archivado');

        // La papelera lo muestra y permite restaurar.
        $this->get(route('projects.trash'))->assertOk()->assertSee('Proyecto archivado');

        $this->post(route('projects.restore', $project))
            ->assertRedirect(route('projects.show', $project));

        $this->assertDatabaseHas('projects', ['id' => $project->id, 'deleted_at' => null]);
        $this->get(route('projects.index'))->assertOk()->assertSee('Proyecto archivado');

        $restoredProject = Project::query()->findOrFail($project->id);
        $this->assertTrue($restoredProject->requirements()->whereKey($requirement->id)->exists());
        $this->assertTrue($restoredProject->architectures()->whereKey($architecture->id)->exists());
        $this->assertTrue($architecture->items()->whereKey($architectureItem->id)->exists());
    }

    public function test_deleted_project_pages_are_not_reachable_with_normal_links(): void
    {
        $user = User::factory()->create();
        $region = $this->createRegion();
        $project = Project::factory()->create([
            'user_id' => $user->id,
            'region_id' => $region->id,
        ]);
        $project->delete();

        $this->actingAs($user)->get(route('projects.show', $project))->assertNotFound();
        $this->get(route('projects.edit', $project))->assertNotFound();
    }

    public function test_owner_cannot_access_another_users_project(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $region = $this->createRegion();
        $foreign = Project::factory()->create([
            'user_id' => $other->id,
            'region_id' => $region->id,
            'name' => 'Proyecto ajeno',
        ]);

        $this->actingAs($user)->get(route('projects.show', $foreign))->assertForbidden();
        $this->actingAs($user)->put(route('projects.update', $foreign), [
            'name' => 'Robo',
            'description' => 'Intento ilegal',
            'region_id' => $foreign->region_id,
            'max_budget' => 10,
            'expected_users' => 1,
        ])->assertForbidden();
        $this->actingAs($user)->delete(route('projects.destroy', $foreign))->assertForbidden();
        $this->assertDatabaseHas('projects', ['id' => $foreign->id, 'name' => 'Proyecto ajeno']);

        // No aparece en su listado.
        $this->get(route('projects.index'))->assertOk()->assertDontSee('Proyecto ajeno');
    }

    public function test_restore_only_accepts_the_owner(): void
    {
        $owner = User::factory()->create();
        $region = $this->createRegion();
        $project = Project::factory()->create([
            'user_id' => $owner->id,
            'region_id' => $region->id,
            'name' => 'Proyecto en papelera',
        ]);
        $project->delete();

        $this->actingAs($this->anotherCliente())
            ->post(route('projects.restore', $project))
            ->assertForbidden();

        $this->actingAs($owner)
            ->post(route('projects.restore', $project))
            ->assertRedirect(route('projects.show', $project));

        $this->assertDatabaseHas('projects', ['id' => $project->id, 'deleted_at' => null]);
    }

    public function test_creating_project_accepts_and_normalizes_max_budget_with_comma(): void
    {
        $user = User::factory()->create();
        $region = $this->createRegion();

        $response = $this->actingAs($user)->post(route('projects.store'), [
            'name' => 'Proyecto con coma',
            'description' => 'Presupuesto introducido con separador decimal en coma.',
            'region_id' => $region->id,
            'max_budget' => '2500,50',
            'expected_users' => 100,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('projects', [
            'name' => 'Proyecto con coma',
            'max_budget' => 2500.50,
        ]);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Requirement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesCatalogData;
use Tests\TestCase;

/**
 * CRUD de requerimientos (RF03/CU03) anidado en el proyecto:
 * combinaciones tipo de dato ↔ operador ↔ valor validadas en el
 * servidor, procedencia `source` impuesta (nunca falseable por el
 * cliente) y acceso exclusivo del propietario (RF17/RN09).
 */
class RequirementTest extends TestCase
{
    use CreatesCatalogData, RefreshDatabase;

    private function projectFor(User $user): Project
    {
        return Project::factory()->create([
            'user_id' => $user->id,
            'region_id' => $this->createRegion()->id,
        ]);
    }

    /**
     * Los seis datos funcionales (RF03) se guardan; `source` lo impone
     * el servidor y `project_id` viene de la ruta, no del formulario.
     */
    public function test_owner_creates_requirement_and_server_forces_source_and_project(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $other = User::factory()->create();
        $foreignProject = $this->projectFor($other);

        $response = $this->actingAs($user)->post(route('projects.requirements.store', $project), [
            'field_key' => 'almacenamiento_gb',
            'operator' => 'gte',
            'value' => '500',
            'data_type' => 'integer',
            'mandatory' => '1',
            // Datos hostiles que el servidor debe ignorar:
            'source' => 'ia', // el usuario no puede falsear la procedencia
            'project_id' => $foreignProject->id, // no puede apuntar a otro proyecto
        ]);

        $response->assertRedirect();
        $requirement = Requirement::query()->where('project_id', $project->id)->firstOrFail();

        $this->assertDatabaseHas('requirements', [
            'id' => $requirement->id,
            'field_key' => 'almacenamiento_gb',
            'operator' => 'gte',
            'value' => '500',
            'data_type' => 'integer',
            'mandatory' => 1,
            'source' => 'manual', // impuesto por el servidor
            'project_id' => $project->id,
        ]);
        $this->assertDatabaseMissing('requirements', ['project_id' => $foreignProject->id]);
    }

    public function test_missing_fields_blocks_saving(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);

        $this->actingAs($user)->post(route('projects.requirements.store', $project), [])
            ->assertSessionHasErrors(['field_key', 'data_type', 'operator', 'value']);

        $this->assertDatabaseCount('requirements', 0);
    }

    /**
     * La matriz tipo ↔ operador se revalida en el servidor aunque el
     * formulario no la ofrezca (el HTML no es el guardián).
     */
    public function test_incompatible_type_and_operator_combination_is_rejected(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);

        $this->actingAs($user)->post(route('projects.requirements.store', $project), [
            'field_key' => 'cifrado_activo',
            'operator' => 'gt', // no admite booleanos
            'value' => '1',
            'data_type' => 'boolean',
        ])->assertSessionHasErrors(['operator']);

        $this->assertDatabaseCount('requirements', 0);
    }

    /**
     * El valor debe tener el formato del tipo de dato elegido.
     */
    public function test_value_with_wrong_format_for_the_type_is_rejected(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);

        $this->actingAs($user)->post(route('projects.requirements.store', $project), [
            'field_key' => 'usuarios',
            'operator' => 'gte',
            'value' => 'numero entero', // no es un entero
            'data_type' => 'integer',
        ])->assertSessionHasErrors(['value']);

        $this->assertDatabaseCount('requirements', 0);
    }

    /**
     * Los decimales escritos con coma se normalizan a la forma canónica
     * con punto antes de almacenarlos.
     */
    public function test_decimal_value_with_comma_is_normalized_on_saving(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);

        $this->actingAs($user)->post(route('projects.requirements.store', $project), [
            'field_key' => 'latencia_maxima_ms',
            'operator' => 'lte',
            'value' => '12,5',
            'data_type' => 'decimal',
        ])->assertRedirect();

        $this->assertDatabaseHas('requirements', [
            'project_id' => $project->id,
            'value' => '12.5',
            'data_type' => 'decimal',
            'operator' => 'lte',
        ]);
    }

    public function test_owner_edits_requirement(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $requirement = Requirement::factory()->create([
            'project_id' => $project->id,
            'field_key' => 'memoria_gb',
            'operator' => 'gte',
            'value' => '16',
            'data_type' => 'integer',
            'mandatory' => true,
        ]);

        $this->actingAs($user)
            ->put(route('projects.requirements.update', [$project, $requirement]), [
                'field_key' => 'memoria_gb',
                'operator' => 'gte',
                'value' => '32',
                'data_type' => 'integer',
                // checkbox ausente => condicion opcional
            ])
            ->assertRedirect(route('projects.requirements.show', [$project, $requirement]));

        $this->assertDatabaseHas('requirements', [
            'id' => $requirement->id,
            'value' => '32',
            'mandatory' => 0,
            'source' => 'manual', // la edición no cambia la procedencia
        ]);
    }

    /**
     * Un cambio incompatible en el formulario de edición se rechaza sin
     * dañar el requerimiento almacenado.
     */
    public function test_invalid_edition_does_not_corrupt_the_saved_requirement(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $requirement = Requirement::factory()->create([
            'project_id' => $project->id,
            'operator' => 'gte',
            'value' => '16',
            'data_type' => 'integer',
        ]);

        $this->actingAs($user)
            ->put(route('projects.requirements.update', [$project, $requirement]), [
                'field_key' => 'memoria_gb',
                'operator' => 'contains', // incompatible con integer
                'value' => '16',
                'data_type' => 'integer',
            ])
            ->assertSessionHasErrors(['operator']);

        $this->assertDatabaseHas('requirements', [
            'id' => $requirement->id,
            'operator' => 'gte',
            'source' => 'manual',
        ]);
    }

    public function test_owner_deletes_requirement_and_project_keeps_intact(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $requirement = Requirement::factory()->create(['project_id' => $project->id]);

        $response = $this->actingAs($user)->delete(route('projects.requirements.destroy', [$project, $requirement]));

        $response->assertRedirect(route('projects.requirements.index', $project));
        $this->assertDatabaseMissing('requirements', ['id' => $requirement->id]);
        $this->assertDatabaseHas('projects', ['id' => $project->id, 'deleted_at' => null]);
    }

    /**
     * Confirmación de borrado con página dedicada, sin JavaScript.
     */
    public function test_delete_confirmation_page_shows_without_javascript(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $requirement = Requirement::factory()->create(['project_id' => $project->id]);

        $this->actingAs($user)
            ->get(route('projects.requirements.delete', [$project, $requirement]))
            ->assertOk()
            ->assertSee('¿Eliminar este requerimiento?');
    }

    /**
     * El listado y el detalle muestran el requerimiento con su etiqueta
     * de operador en español.
     */
    public function test_index_and_show_pages_render_the_requirement(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $requirement = Requirement::factory()->create([
            'project_id' => $project->id,
            'field_key' => 'usuarios_simultaneos',
            'operator' => 'gte',
        ]);

        $this->actingAs($user)
            ->get(route('projects.requirements.index', $project))
            ->assertOk()
            ->assertSee('usuarios_simultaneos');

        $this->get(route('projects.requirements.show', [$project, $requirement]))
            ->assertOk()
            ->assertSee('usuarios_simultaneos')
            ->assertSee('mayor o igual que'); // etiqueta es de RequirementRules
    }

    /**
     * Un usuario externo no puede ver ni modificar los requerimientos
     * de otro (RF17/RN09): 404 no filtrable (403) para escritura o
     * lectura directa, manteniendo el registro intacto.
     */
    public function test_foreign_user_cannot_access_or_modify_requirements(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $requirement = Requirement::factory()->create([
            'project_id' => $project->id,
            'field_key' => 'secreto',
        ]);

        $this->actingAs($this->anotherCliente())
            ->get(route('projects.requirements.index', $project))
            ->assertForbidden();
        $this->get(route('projects.requirements.create', $project))->assertForbidden();
        $this->get(route('projects.requirements.show', [$project, $requirement]))->assertForbidden();
        $this->get(route('projects.requirements.edit', [$project, $requirement]))->assertForbidden();
        $this->get(route('projects.requirements.delete', [$project, $requirement]))->assertForbidden();

        $this->put(route('projects.requirements.update', [$project, $requirement]), [
            'field_key' => 'pirata',
            'operator' => 'eq',
            'value' => '1',
            'data_type' => 'boolean',
        ])->assertForbidden();

        $this->delete(route('projects.requirements.destroy', [$project, $requirement]))->assertForbidden();

        $this->assertDatabaseHas('requirements', [
            'id' => $requirement->id,
            'field_key' => 'secreto',
        ]);
    }

    /**
     * Los enlaces reforzados (scoped bindings) impiden resolver el
     * requerimiento de un proyecto ajeno a través de la ruta propia.
     */
    public function test_requirement_of_another_project_is_not_resolvable_through_own_route(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $other = User::factory()->create();
        $foreignRequirement = Requirement::factory()->create([
            'project_id' => $this->projectFor($other)->id,
        ]);

        $this->actingAs($user)
            ->get(route('projects.requirements.show', [$project, $foreignRequirement]))
            ->assertNotFound();

        $this->put(route('projects.requirements.update', [$project, $foreignRequirement]), [
            'field_key' => 'pirata',
            'operator' => 'eq',
            'value' => '1',
            'data_type' => 'boolean',
        ])->assertNotFound();

        $this->assertDatabaseHas('requirements', [
            'id' => $foreignRequirement->id,
            'field_key' => $foreignRequirement->field_key,
        ]);
    }
}

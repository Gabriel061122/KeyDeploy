<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Requirement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesCatalogData;
use Tests\TestCase;

/**
 * Formulario dinámico de requerimientos SIN JavaScript (RF03): la
 * recarga de la página con un GET tradicional (`?data_type=...`)
 * reordena operadores y campo de valor, conserva lo tecleado y el
 * servidor revalida la combinación completa.
 */
class RequirementDynamicFormTest extends TestCase
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
     * Creación del formulario sin datos: primer tipo (cadena),
     * operadores compatibles con cadenas y campo de valor de texto.
     */
    public function test_create_page_defaults_to_string_type_with_text_input(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);

        $this->actingAs($user)
            ->get(route('projects.requirements.create', ['project' => $project]))
            ->assertOk()
            ->assertSee('value="string" selected', false)
            ->assertSee('value="contains"', false) // operador de cadena
            ->assertSee('type="text" name="value"', false);
    }

    /**
     * "Actualizar" con tipo entero recarga los operadores compatibles y
     * el campo de valor numérico: los operadores de cadenas desaparecen.
     */
    public function test_update_button_reloads_compatible_operators_for_integer_type(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);

        $this->actingAs($user)
            ->get(route('projects.requirements.create', ['project' => $project, 'data_type' => 'integer']))
            ->assertOk()
            ->assertSee('value="integer" selected', false)
            ->assertSee('value="gte"', false)
            ->assertDontSee('value="contains"', false)
            ->assertDontSee('value="not_contains"', false)
            ->assertSee('type="number" name="value" step="1"', false);
    }

    /**
     * El tipo decimal usa un input numérico con decimales permitidos;
     * el tipo booleano cambia a un selector Verdadero/Falso.
     */
    public function test_value_field_adapts_to_decimal_and_boolean_types(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);

        $this->actingAs($user)
            ->get(route('projects.requirements.create', ['project' => $project, 'data_type' => 'decimal']))
            ->assertOk()
            ->assertSee('value="decimal" selected', false)
            ->assertSee('type="number" name="value" step="any"', false);

        $this->get(route('projects.requirements.create', ['project' => $project, 'data_type' => 'boolean']))
            ->assertOk()
            ->assertSee('<option value="1">Verdadero</option>', false)
            ->assertSee('<option value="0">Falso</option>', false)
            ->assertDontSee('type="number" name="value"', false)
            ->assertDontSee('type="text" name="value"', false);
    }

    /**
     * Durante la recarga de tipo se conserva lo ya tecleado mediante
     * campos ocultos (sin JavaScript).
     */
    public function test_type_reload_preserves_typed_data_in_hidden_fields(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);

        $this->actingAs($user)
            ->get(route('projects.requirements.create', [
                'project' => $project,
                'data_type' => 'integer',
                'operator' => 'gte',
                'value' => '25',
                'field_key' => 'nucleos_cpu',
                'mandatory' => '1',
            ]))
            ->assertOk()
            ->assertSee('<input type="hidden" name="field_key" value="nucleos_cpu">', false)
            ->assertSee('<input type="hidden" name="operator" value="gte">', false)
            ->assertSee('<input type="hidden" name="value" value="25">', false)
            ->assertSee('<input type="hidden" name="mandatory" value="1">', false)
            // y el formulario principal se rellena con lo conservado:
            ->assertSee('name="field_key" value="nucleos_cpu"', false)
            ->assertSee('type="number" name="value" step="1" value="25"', false)
            ->assertSee('value="gte" selected', false)
            ->assertSee('name="mandatory" value="1" checked', false);
    }

    /**
     * Un enlace manipulado con una combinación incompatible (booleano +
     * mayor que) se corrige al recargar: el operador se descarta.
     */
    public function test_incompatible_operator_from_a_tampered_link_is_reset(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);

        $this->actingAs($user)
            ->get(route('projects.requirements.create', [
                'project' => $project,
                'data_type' => 'boolean',
                'operator' => 'gt', // incompatible con booleanos
                'value' => '1',
            ]))
            ->assertOk()
            ->assertSee('— Elige un operador —', false) // operador descartado
            ->assertDontSee('value="gt"', false);
    }

    /**
     * Tras una validación fallida, lo tecleado se conserva en el
     * formulario mediante los datos antiguos de la sesión.
     */
    public function test_failed_validation_keeps_user_input_through_old_data(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);

        $this->actingAs($user)->post(route('projects.requirements.store', $project), [
            'field_key' => 'almacenamiento_gb',
            'operator' => 'gte',
            'value' => '', // vacío: error obligatorio
            'data_type' => 'integer',
        ])->assertSessionHasErrors(['value']);

        // El usuario vuelve al formulario sin parámetros: el campo y la
        // combinación elegida siguen ahí.
        $this->get(route('projects.requirements.create', ['project' => $project]))
            ->assertOk()
            ->assertSee('value="almacenamiento_gb"', false)
            ->assertSee('value="integer" selected', false)
            ->assertSee('value="gte" selected', false);
    }

    /**
     * El formulario de edición se rellena con los datos del
     * requerimiento existente.
     */
    public function test_edit_page_prefills_the_requirement_data(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $requirement = Requirement::factory()->create([
            'project_id' => $project->id,
            'field_key' => 'ancho_banda_mbps',
            'operator' => 'gte',
            'value' => '250',
            'data_type' => 'integer',
            'mandatory' => true,
        ]);

        $this->actingAs($user)
            ->get(route('projects.requirements.edit', ['project' => $project, 'requirement' => $requirement]))
            ->assertOk()
            ->assertSee('name="field_key" value="ancho_banda_mbps"', false)
            ->assertSee('value="integer" selected', false)
            ->assertSee('value="gte" selected', false)
            ->assertSee('type="number" name="value" step="1" value="250"', false)
            ->assertSee('name="mandatory" value="1" checked', false);
    }

    /**
     * En edición, cambiar el tipo incompatible también descarta el
     * operador guardado y recarga con el campo adaptado.
     */
    public function test_edit_page_reloads_and_resets_operator_for_a_new_incompatible_type(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $requirement = Requirement::factory()->create([
            'project_id' => $project->id,
            'operator' => 'gte',
            'value' => '250',
            'data_type' => 'integer',
        ]);

        $this->actingAs($user)
            ->get(route('projects.requirements.edit', [
                'project' => $project,
                'requirement' => $requirement,
                'data_type' => 'string',
            ]))
            ->assertOk()
            ->assertSee('value="string" selected', false)
            ->assertSee('— Elige un operador —', false) // "gte" ya no es válido
            ->assertDontSee('value="gte" selected', false)
            ->assertSee('type="text" name="value" value="250"', false); // valor conservado
    }

    /**
     * Aunque un cliente adultere el HTML y envíe una combinación
     * prohibida, el servidor la rechaza al guardar.
     */
    public function test_server_revalidates_the_browser_visible_combination_on_save(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);

        $this->actingAs($user)->post(route('projects.requirements.store', $project), [
            'field_key' => 'texto',
            'operator' => 'gte', // prohibido para cadenas
            'value' => 'PostgreSQL',
            'data_type' => 'string',
        ])->assertSessionHasErrors(['operator']);

        $this->assertDatabaseCount('requirements', 0);
    }
}

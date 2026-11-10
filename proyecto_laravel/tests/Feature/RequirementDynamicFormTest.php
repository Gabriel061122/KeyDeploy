<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Requirement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\CreatesCatalogData;
use Tests\TestCase;

/**
 * Formulario dinámico de requerimientos SIN JavaScript (RF03), contrato
 * de formulario único: el envío principal (POST alta / PUT edición)
 * incluye SIEMPRE el campo `data_type`; el botón «Actualizar» usa
 * `formaction` a las rutas de recarga (`projects.requirements.refresh`
 * y `projects.requirements.refresh.edit`) con `formnovalidate`, y la
 * recarga devuelve al formulario con la entrada antigua sin guardar.
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
     * Criterio 13: el formulario de alta es ÚNICO (POST) e incluye el
     * tipo de dato (`data_type`) en el propio envío; «Actualizar»
     * recarga por POST a la ruta de recarga con `formnovalidate`.
     */
    public function test_create_form_is_single_and_includes_data_type(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);

        $html = $this->actingAs($user)
            ->get(route('projects.requirements.create', ['project' => $project]))
            ->assertOk()
            ->getContent();

        // El formulario principal (POST hacia el alta) contiene el
        // campo data_type: elegir el tipo nunca depende de otro envío.
        $this->assertMatchesRegularExpression(
            '/<form\b[^>]*action=["\']'.preg_quote(route('projects.requirements.store', $project), '/').'["\'][^>]*>(?:(?!<\/form>).)*name="data_type"/s',
            $html,
            'El formulario principal de alta debe incluir el campo data_type.',
        );

        // Botón «Actualizar»: recarga POST independiente, sin
        // validación, con la entrada antigua.
        $this->assertMatchesRegularExpression(
            '/<button\b[^>]*formaction=["\']'.preg_quote(route('projects.requirements.refresh', $project), '/').'["\']/i',
            $html,
        );
        $this->assertMatchesRegularExpression('/formnovalidate/i', $html);
    }

    /**
     * Criterio 14, permisos intactos: la recarga del tipo exige el
     * propietario del proyecto en alta y en edición; nada se guarda.
     */
    public function test_refresh_routes_are_owner_only(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $requirement = Requirement::factory()->create(['project_id' => $project->id]);

        $this->actingAs($this->anotherCliente())
            ->post(route('projects.requirements.refresh', $project), ['data_type' => 'integer'])
            ->assertForbidden();

        $this->actingAs($this->anotherCliente())
            ->put(route('projects.requirements.refresh.edit', [$project, $requirement]), ['data_type' => 'string'])
            ->assertForbidden();

        $this->assertDatabaseHas('requirements', [
            'id' => $requirement->id,
            'field_key' => $requirement->field_key,
        ]);
    }

    /**
     * Criterio 13: «Actualizar» con tipo entero recarga los operadores
     * compatibles y el campo de valor numérico; los operadores de
     * cadenas desaparecen. La recarga no guarda nada.
     */
    public function test_update_button_reloads_compatible_operators_for_integer_type(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);

        $this->actingAs($user)
            ->post(route('projects.requirements.refresh', $project), [
                'data_type' => 'integer',
                'field_key' => 'usuarios_simultaneos',
                'operator' => 'gte',
                'value' => '25',
            ])
            ->assertRedirect(route('projects.requirements.create', $project));

        $this->get(route('projects.requirements.create', $project))
            ->assertOk()
            ->assertSee('value="integer" selected', false)
            ->assertSee('value="gte"', false)
            ->assertDontSee('value="contains"', false)
            ->assertDontSee('value="not_contains"', false)
            ->assertSee('type="number" name="value" step="1"', false);

        $this->assertDatabaseCount('requirements', 0);
    }

    /**
     * Criterio 13: el tipo decimal usa un input numérico con decimales
     * permitidos; el tipo booleano cambia a un selector Verdadero/Falso.
     * La adaptación ocurre con la recarga por la ruta de recarga.
     */
    public function test_value_field_adapts_to_decimal_and_boolean_types(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);

        $this->postTypeAndReload($user, $project, 'decimal')
            ->assertOk()
            ->assertSee('value="decimal" selected', false)
            ->assertSee('type="number" name="value" step="any"', false);

        $this->postTypeAndReload($user, $project, 'boolean')
            ->assertOk()
            ->assertSee('<option value="1">Verdadero</option>', false)
            ->assertSee('<option value="0">Falso</option>', false)
            ->assertDontSee('type="number" name="value"', false)
            ->assertDontSee('type="text" name="value"', false);

        $this->assertDatabaseCount('requirements', 0);
    }

    /**
     * Criterio 13: la recarga del tipo conserva lo ya tecleado (campo,
     * operador, valor y obligatoriedad) sin guardar.
     */
    public function test_type_reload_conserves_the_typed_data(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);

        $this->actingAs($user)
            ->post(route('projects.requirements.refresh', $project), [
                'data_type' => 'integer',
                'field_key' => 'nucleos_cpu',
                'operator' => 'gte',
                'value' => '25',
                'mandatory' => '1',
            ])
            ->assertRedirect(route('projects.requirements.create', $project));

        $this->get(route('projects.requirements.create', $project))
            ->assertOk()
            ->assertSee('name="field_key" value="nucleos_cpu"', false)
            ->assertSee('type="number" name="value" step="1" value="25"', false)
            ->assertSee('value="gte" selected', false)
            ->assertSee('name="mandatory" value="1" checked', false);

        $this->assertDatabaseCount('requirements', 0);
    }

    /**
     * Una combinación incompatible enviada a la recarga (booleano +
     * mayor que) se corrige al recargar: el operador se descarta y el
     * campo de valor se adapta al nuevo tipo.
     */
    public function test_incompatible_operator_after_a_type_change_is_reset_by_the_reload(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);

        $this->postTypeAndReload($user, $project, 'boolean', operator: 'gt', value: '1')
            ->assertOk()
            ->assertSee('— Elige un operador —', false) // operador descartado
            ->assertDontSee('value="gt"', false)
            ->assertSee('<option value="1">Verdadero</option>', false);

        $this->assertDatabaseCount('requirements', 0);
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
     * Criterio 13: el formulario de edición se rellena con los datos
     * del requerimiento existente y tiene envío principal (PUT) con
     * `data_type`; «Actualizar» usa formaction a la recarga de edición.
     */
    public function test_edit_page_prefills_the_requirement_and_includes_data_type(): void
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

        $html = $this->actingAs($user)
            ->get(route('projects.requirements.edit', ['project' => $project, 'requirement' => $requirement]))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/<form\b[^>]*action=["\']'.preg_quote(route('projects.requirements.update', [$project, $requirement]), '/').'["\'][^>]*>(?:(?!<\/form>).)*name="data_type"/s',
            $html,
            'El formulario principal de edición debe incluir el campo data_type.',
        );
        $this->assertMatchesRegularExpression(
            '/<button\b[^>]*formaction=["\']'.preg_quote(route('projects.requirements.refresh.edit', [$project, $requirement]), '/').'["\']/i',
            $html,
        );

        $this->get(route('projects.requirements.edit', ['project' => $project, 'requirement' => $requirement]))
            ->assertOk()
            ->assertSee('name="field_key" value="ancho_banda_mbps"', false)
            ->assertSee('value="integer" selected', false)
            ->assertSee('value="gte" selected', false)
            ->assertSee('type="number" name="value" step="1" value="250"', false)
            ->assertSee('name="mandatory" value="1" checked', false);
    }

    /**
     * Criterio 13: en edición, «Actualizar» recarga conservando campo,
     * tipo, operador, valor y obligatoriedad (con entrada antigua) SIN
     * guardar cambios en el requerimiento existente.
     */
    public function test_edit_type_reload_conserves_input_and_saves_nothing(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $requirement = Requirement::factory()->create([
            'project_id' => $project->id,
            'field_key' => 'ancho_banda_mbps',
            'operator' => 'gte',
            'value' => '250',
            'data_type' => 'integer',
        ]);

        $this->actingAs($user)
            ->put(route('projects.requirements.refresh.edit', [$project, $requirement]), [
                'data_type' => 'string',
                'field_key' => 'ancho_banda_mbps',
                'operator' => 'contains',
                'value' => 'fibra',
            ])
            ->assertRedirect(route('projects.requirements.edit', [$project, $requirement]));

        // La recarga de edición NO guarda nada.
        $this->assertDatabaseHas('requirements', [
            'id' => $requirement->id,
            'data_type' => 'integer',
            'operator' => 'gte',
            'value' => '250',
        ]);
        $this->assertDatabaseCount('requirements', 1);

        $this->get(route('projects.requirements.edit', ['project' => $project, 'requirement' => $requirement]))
            ->assertOk()
            ->assertSee('value="string" selected', false)
            ->assertSee('name="field_key" value="ancho_banda_mbps"', false)
            ->assertSee('value="contains"', false)
            ->assertSee('name="value" value="fibra"', false);
    }

    /**
     * En edición, cambiar el tipo a uno incompatible descarta el
     * operador guardado durante la recarga (sin dañar el registro).
     */
    public function test_edit_reload_resets_the_saved_operator_for_a_new_incompatible_type(): void
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
            ->put(route('projects.requirements.refresh.edit', [$project, $requirement]), [
                'data_type' => 'string',
            ])
            ->assertRedirect(route('projects.requirements.edit', [$project, $requirement]));

        $this->get(route('projects.requirements.edit', ['project' => $project, 'requirement' => $requirement]))
            ->assertOk()
            ->assertSee('value="string" selected', false)
            ->assertSee('— Elige un operador —', false) // "gte" ya no es válido
            ->assertDontSee('value="gte" selected', false)
            ->assertSee('name="value" value="250"', false); // valor conservado

        $this->assertDatabaseHas('requirements', [
            'id' => $requirement->id,
            'data_type' => 'integer',
            'operator' => 'gte',
        ]);
    }

    /**
     * Envía la recarga del tipo por su ruta (alta) y devuelve la carga
     * del formulario de alta resultante.
     */
    private function postTypeAndReload(User $user, Project $project, string $dataType, string $operator = '', string $value = ''): TestResponse
    {
        $this->actingAs($user)
            ->post(route('projects.requirements.refresh', $project), array_filter([
                'data_type' => $dataType,
                'field_key' => 'campo_recargado',
                'operator' => $operator,
                'value' => $value,
            ], fn ($v) => $v !== '' && $v !== null));

        return $this->get(route('projects.requirements.create', $project));
    }
}

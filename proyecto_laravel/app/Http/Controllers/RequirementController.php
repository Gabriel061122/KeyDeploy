<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRequirementRequest;
use App\Http\Requests\UpdateRequirementRequest;
use App\Models\Project;
use App\Models\Requirement;
use App\Services\RequirementService;
use App\Services\RequirementRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * CRUD de requerimientos ellos de un Proyecto (RF03/CU03), anidado en
 * la ruta del proyecto. Los seis datos funcionales son: field_key,
 * operator, value, data_type, mandatory y source; los dos últimos se
 * controlan en el servidor (propiedad del proyecto y procedencia).
 */
class RequirementController extends Controller
{
    public function __construct(private RequirementService $requirementService) {}

    public function index(Project $project): View
    {
        $this->authorize('view', $project);

        return view('requirements.index', [
            'project' => $project->load('region'),
            'requirements' => $project->requirements()->orderBy('id')->get(),
        ]);
    }

    public function create(Project $project, Request $request): View
    {
        $this->authorize('view', $project);

        [$selectedType, $selectedOperator, $value, $mandatory, $fieldKey] = $this->formState($request, $this->types());

        return view('requirements.create', [
            'project' => $project,
            'types' => $this->types(),
            'operators' => RequirementRules::operatorsFor($selectedType),
            'operatorLabels' => RequirementRules::OPERATOR_LABELS,
            'selectedType' => $selectedType,
            'selectedOperator' => $selectedOperator,
            'value' => $value,
            'mandatory' => $mandatory,
            'fieldKey' => $fieldKey,
        ]);
    }

    /**
     * Guarda el requerimiento tras revalidar por completo la combinación
     * tipo de dato + operador + valor en el servidor (StoreRequirementRequest).
     */
    public function store(StoreRequirementRequest $request, Project $project): RedirectResponse
    {
        $requirement = $this->requirementService->create(
            $project,
            $request->validated(),
            $request->boolean('mandatory'),
        );

        return redirect()
            ->route('projects.requirements.show', [$project, $requirement])
            ->with('success', 'Requerimiento creado correctamente.');
    }

    public function show(Project $project, Requirement $requirement): View
    {
        $this->authorize('view', $project);

        return view('requirements.show', [
            'project' => $project,
            'requirement' => $requirement,
            'operatorLabels' => RequirementRules::OPERATOR_LABELS,
        ]);
    }

    public function edit(Project $project, Requirement $requirement, Request $request): View
    {
        $this->authorize('view', $project);

        [$selectedType, $selectedOperator, $value, $mandatory, $fieldKey] = $this->formState($request, $this->types(), $requirement);
        $operators = RequirementRules::operatorsFor($selectedType);

        return view('requirements.edit', [
            'project' => $project,
            'requirement' => $requirement,
            'types' => $this->types(),
            'operators' => $operators,
            'operatorLabels' => RequirementRules::OPERATOR_LABELS,
            'selectedType' => $selectedType,
            'selectedOperator' => $selectedOperator,
            'value' => $value,
            'mandatory' => $mandatory,
            'fieldKey' => $fieldKey,
        ]);
    }

    public function update(UpdateRequirementRequest $request, Project $project, Requirement $requirement): RedirectResponse
    {
        $this->requirementService->update(
            $project,
            $requirement,
            $request->validated(),
            $request->boolean('mandatory'),
        );

        return redirect()
            ->route('projects.requirements.show', [$project, $requirement])
            ->with('success', 'Requerimiento actualizado.');
    }

    /**
     * Elimina el requerimiento; el resto del proyecto no se ve
     * afectado. La confirmación previa es una página dedicada (los
     * formularios no dependen de JavaScript).
     */
    public function destroy(Project $project, Requirement $requirement): RedirectResponse
    {
        $this->authorize('delete', $requirement);

        $this->requirementService->delete($project, $requirement);

        return redirect()
            ->route('projects.requirements.index', $project)
            ->with('success', 'Requerimiento eliminado.');
    }

    public function confirmDelete(Project $project, Requirement $requirement): View
    {
        $this->authorize('delete', $requirement);

        return view('requirements.delete', [
            'project' => $project,
            'requirement' => $requirement,
        ]);
    }

    /**
     * Estado del formulario dinámico sin JavaScript: el tipo llega por
     * GET (botón "Actualizar"), y tras validar fallida (old) el valor
     * tecleado se conserva. La combinación vuelve a validarse al enviar
     * con el Form Request, sin confiar en las opciones de Blade.
     *
     * @return array{0: string, 1: string, 2: string, 3: bool, 4: string}
     */
    private function formState(Request $request, array $types, ?Requirement $requirement = null): array
    {
        $fieldKey = $request->input('field_key') ?? $request->old('field_key') ?? $requirement?->field_key ?? '';

        $selectedType = $request->input('data_type') ?? $request->old('data_type') ?? $requirement?->data_type ?? $types[0];
        if (! in_array($selectedType, $types, true)) {
            $selectedType = $types[0];
        }

        $selectedOperator = $request->input('operator') ?? $request->old('operator') ?? $requirement?->operator ?? '';
        if (! RequirementRules::isCompatible($selectedType, (string) $selectedOperator)) {
            $selectedOperator = '';
        }
        $value = $request->input('value') ?? $request->old('value') ?? $requirement?->value ?? '';
        $mandatory = (bool) ($request->input('mandatory') ?? $request->old('mandatory') ?? $requirement?->mandatory);

        return [$selectedType, $selectedOperator ?? '', (string) $value, $mandatory, (string) $fieldKey];
    }

    /**
     * @return list<string>
     */
    private function types(): array
    {
        return RequirementRules::TYPES;
    }
}

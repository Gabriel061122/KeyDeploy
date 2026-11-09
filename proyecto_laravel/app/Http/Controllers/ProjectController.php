<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Models\Project;
use App\Models\Region;
use App\Services\ProjectService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * CRUD de Proyectos del CLIENTE (RF02/CU02).
 *
 * Seguridad: el propietario se fija siempre en el servidor y toda
 * acción pasa por ProjectPolicy (RF17/RN09). El borrado es lógico
 * (SoftDeletes) para conservar requerimientos, arquitecturas y cualquier
 * registro histórico asociado; la restauración, cuando proceda, queda
 * en manos del propietario desde la papelera.
 */
class ProjectController extends Controller
{
    public function __construct(private ProjectService $projectService) {}

    public function index(Request $request): View
    {
        $projects = Project::query()
            ->ownedBy($request->user())
            ->with('region')
            ->withCount(['requirements', 'architectures'])
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        return view('projects.index', ['projects' => $projects]);
    }

    public function create(): View
    {
        return view('projects.create', [
            'regions' => $this->regions(),
        ]);
    }

    public function store(StoreProjectRequest $request): RedirectResponse
    {
        $project = $this->projectService->create($request->user(), $request->validated());

        return redirect()
            ->route('projects.show', $project)
            ->with('success', 'Proyecto "'.$project->name.'" creado correctamente.');
    }

    public function show(Project $project): View
    {
        $this->authorize('view', $project);

        $project->load('region');
        $requirements = $project->requirements()->orderBy('id')->get();
        $architectures = $project->architectures()->with(['region', 'items.product'])->get();

        return view('projects.show', [
            'project' => $project,
            'requirements' => $requirements,
            'architectures' => $architectures,
        ]);
    }

    public function edit(Project $project): View
    {
        $this->authorize('update', $project);

        return view('projects.edit', [
            'project' => $project,
            'regions' => $this->regions(),
        ]);
    }

    public function update(UpdateProjectRequest $request, Project $project): RedirectResponse
    {
        $this->projectService->update($project, $request->validated());

        return redirect()
            ->route('projects.show', $project)
            ->with('success', 'Proyecto "'.$project->name.'" actualizado.');
    }

    /**
     * Borrado lógico: el proyecto desaparece de los listados pero sus
     * requerimientos, arquitecturas y resto de dependencias permanecen
     * intactas para historicidad y para una restauración futura.
     */
    public function destroy(Project $project): RedirectResponse
    {
        $this->authorize('delete', $project);

        $name = $project->name;
        $this->projectService->delete($project);

        return redirect()
            ->route('projects.index')
            ->with('success', 'Proyecto "'.$name.'" movido a la papelera. Sus requerimientos y arquitecturas se conservan.');
    }

    /**
     * Confirmación previa al borrado, sin JavaScript: página dedicada
     * que explica las consecuencias y con confirmación explícita.
     */
    public function confirmDelete(Project $project): View
    {
        $this->authorize('delete', $project);

        return view('projects.delete', ['project' => $project]);
    }

    /**
     * Papelera de proyectos borrados del usuario autenticado.
     */
    public function trash(Request $request): View
    {
        $projects = Project::query()
            ->onlyTrashed()
            ->ownedBy($request->user())
            ->with('region')
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        return view('projects.trash', ['projects' => $projects]);
    }

    /**
     * Restaura un proyecto borrado (el resto de datos nunca se tocó).
     */
    public function restore(Project $project): RedirectResponse
    {
        $this->authorize('restore', $project);

        $this->projectService->restore($project);

        return redirect()
            ->route('projects.show', $project)
            ->with('success', 'Proyecto "'.$project->name.'" restaurado.');
    }

    /**
     * @return Collection<int, Region>
     */
    private function regions()
    {
        return Region::query()->orderBy('name')->get();
    }
}

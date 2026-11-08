<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreArchitectureRequest;
use App\Models\Architecture;
use App\Models\Product;
use App\Models\Project;
use App\Models\Region;
use App\Services\ArchitectureValidator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Creación manual de arquitecturas para un Proyecto propio, sin
 * depender de la IA (complemento manual de los flujos RF04/CU04).
 *
 * Flujo sin JavaScript: formulario → (POST) validación completa con
 * StoreArchitectureRequest y ArchitectureValidator → previsualización
 * (GET sobre un borrador en sesión) → confirmación → guardado atómico
 * con DB::transaction (arquitectura + todos sus elementos). Nunca se
 * persiste una arquitectura parcialmente válida.
 */
class ArchitectureController extends Controller
{
    /** Clave del borrador pendiente de confirmar en la sesión. */
    public const DRAFT_KEY = 'architecture_draft';

    public function __construct(public ArchitectureValidator $architectureValidator) {}

    /**
     * Formulario de alta: región, productos disponibles (filtrados por
     * la región elegida, recargando la página con un GET) y una fila por
     * elemento con producto, cantidad y configuración JSON individual.
     *
     * Si existe un borrador pendiente de revisión, sus valores rellenan
     * el formulario para que "Volver al formulario" no pierda datos.
     */
    public function create(Project $project, Request $request): View
    {
        $this->authorize('view', $project);

        $draft = $request->session()->get(self::DRAFT_KEY);

        $regions = Region::query()->orderBy('name')->get();

        // La región puede venir del GET (botón "Actualizar"), de los
        // datos antiguos de una validación fallida o del borrador; de la
        // combinación real se vuelve a comprobar todo al guardar.
        $selectedRegionId = $request->input('region_id')
            ?? $request->old('region_id')
            ?? $draft['region_id']
            ?? $project->region_id;
        $selectedRegionId = (int) $selectedRegionId;

        $name = $request->input('name')
            ?? $request->old('name')
            ?? $draft['name']
            ?? '';

        $products = $this->productsFor($regions->firstWhere('id', $selectedRegionId));

        $slots = StoreArchitectureRequest::ITEM_SLOTS;
        $rowState = [];
        $oldItems = $request->old('items', []);
        $draftItems = is_array($draft) ? ($draft['items'] ?? []) : [];

        for ($i = 0; $i < $slots; $i++) {
            $row = is_array($oldItems[$i] ?? null) ? $oldItems[$i] : null;

            if ($row !== null) {
                $rowState[] = [
                    'product_id' => $row['product_id'] ?? null,
                    'quantity' => $row['quantity'] ?? null,
                    'config' => $row['config'] ?? null,
                ];

                continue;
            }

            $draftRow = $draftItems[$i] ?? null;
            if (is_array($draftRow)) {
                $config = $draftRow['config'] ?? null;
                $rowState[] = [
                    'product_id' => $draftRow['product_id'] ?? null,
                    'quantity' => $draftRow['quantity'] ?? null,
                    'config' => is_array($config) ? json_encode($config) : $config,
                ];

                continue;
            }

            $rowState[] = ['product_id' => null, 'quantity' => null, 'config' => null];
        }

        return view('architectures.create', [
            'project' => $project,
            'regions' => $regions,
            'products' => $products,
            'slots' => $slots,
            'rowState' => $rowState,
            'selectedRegionId' => $selectedRegionId,
            'name' => (string) $name,
        ]);
    }

    /**
     * Valida todo el borrador y lo almacena en la sesión para la
     * previsualización: el usuario revisa los elementos antes de
     * confirmar, y el guardado final revalida de nuevo porque la sesión
     * nunca sustituye a la comprobación en el servidor.
     */
    public function preview(StoreArchitectureRequest $request, Project $project): RedirectResponse
    {
        $validated = $request->validated();
        $region = Region::query()->findOrFail($validated['region_id']);

        $result = $this->architectureValidator->validate($region, $validated['items']);

        if (! $result['valid']) {
            // Defensa adicional: el Form Request ya debería haber
            // detectado cualquier error de dominio.
            return back()
                ->withInput()
                ->withErrors(['items' => $result['errors']]);
        }

        $request->session()->put(self::DRAFT_KEY, [
            'project_id' => $project->id,
            'name' => $validated['name'],
            'region_id' => $region->id,
            'items' => $result['items'],
        ]);

        return redirect()->route('projects.architectures.check', $project);
    }

    /**
     * Previsualización del borrador validado: nombre, región y cada
     * elemento (producto, cantidad y configuración) antes de guardar.
     */
    public function check(Request $request, Project $project): View|RedirectResponse
    {
        $this->authorize('view', $project);

        $draft = $request->session()->get(self::DRAFT_KEY);

        if (! is_array($draft) || ($draft['project_id'] ?? null) !== $project->id) {
            return redirect()
                ->route('projects.architectures.create', $project)
                ->withErrors(['items' => 'No hay una arquitectura pendiente de revisión.']);
        }

        $region = Region::query()->findOrFail($draft['region_id']);
        $products = Product::query()
            ->whereIn('id', array_column($draft['items'], 'product_id'))
            ->get()
            ->keyBy('id');

        return view('architectures.preview', [
            'project' => $project,
            'draft' => $draft,
            'region' => $region,
            'products' => $products,
        ]);
    }

    /**
     * Guardado atómico: la arquitectura y todos sus elementos se
     * escriben dentro de una única transacción; si algo falla, nada
     * queda a medio guardar (principio de coherencia de RN08).
     */
    public function store(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('create', [Architecture::class, $project]);

        $draft = $request->session()->get(self::DRAFT_KEY);

        if (! is_array($draft) || ($draft['project_id'] ?? null) !== $project->id) {
            return redirect()
                ->route('projects.architectures.create', $project)
                ->withErrors(['items' => 'No hay una arquitectura pendiente de revisión.']);
        }

        $region = Region::query()->findOrFail($draft['region_id']);
        $result = $this->architectureValidator->validate($region, $draft['items']);

        if (! $result['valid']) {
            return redirect()
                ->route('projects.architectures.create', $project)
                ->withInput()
                ->withErrors(['items' => $result['errors']]);
        }

        $architecture = DB::transaction(function () use ($project, $draft, $result): Architecture {
            $architecture = new Architecture([
                'region_id' => $draft['region_id'],
                'name' => $draft['name'],
            ]);
            $architecture->project_id = $project->id;
            $architecture->source = Architecture::SOURCE_MANUAL;
            $architecture->save();

            $architecture->items()->createMany($result['items']);

            return $architecture;
        });

        $request->session()->forget(self::DRAFT_KEY);

        return redirect()
            ->route('architectures.show', $architecture)
            ->with('success', 'Arquitectura "'.$architecture->name.'" guardada con '.$architecture->items()->count().' elementos.');
    }

    public function index(Project $project): View
    {
        $this->authorize('view', $project);

        return view('architectures.index', [
            'project' => $project,
            'architectures' => $project->architectures()->with(['region', 'items.product'])->get(),
        ]);
    }

    public function show(Architecture $architecture): View
    {
        $this->authorize('view', $architecture);

        $architecture->load(['region', 'items.product', 'project']);

        return view('architectures.show', ['architecture' => $architecture]);
    }

    /**
     * Productos activos y disponibles en la región dada (RN05), en una
     * única consulta con la disponibilidad comprobada frente a la BD: el
     * identificador de región enviado no puede saltarse la comprobación.
     *
     * @return Collection<int, Product>
     */
    private function productsFor(?Region $region)
    {
        if ($region === null) {
            return collect();
        }

        return Product::query()->availableIn($region)->orderBy('name')->get();
    }
}

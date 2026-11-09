<?php

namespace App\Http\Controllers;

use App\Exceptions\PricingException;
use App\Http\Requests\StoreArchitectureRequest;
use App\Models\Architecture;
use App\Models\ArchitectureItem;
use App\Models\Product;
use App\Models\Project;
use App\Models\Region;
use App\Services\ArchitecturePricingService;
use App\Services\ArchitectureValidator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Creación manual de arquitecturas (RN05 / RN08 / EP04).
 *
 * Flujo en tres pasos con pantalla de previsualización antes de guardar:
 * 1. Formulario inicial: región, productos disponibles y filas por elemento.
 * 2. Revisión / previsualización: validación completa y resumen con precios.
 * 3. Confirmación y guardado definitivo: escritura atómica en DB::transaction().
 */
class ArchitectureController extends Controller
{
    public const DRAFT_KEY = 'architecture_draft';

    public function __construct(
        public ArchitectureValidator $architectureValidator,
        public ArchitecturePricingService $pricingService,
    ) {}

    /**
     * Formulario de alta: región, productos disponibles (filtrados por
     * la región elegida, recargando la página con un GET) y una fila por
     * elemento con producto, cantidad y configuración JSON individual.
     */
    public function create(Project $project, Request $request): View
    {
        $this->authorize('view', $project);

        $draft = $request->session()->get(self::DRAFT_KEY);

        $regions = Region::query()->orderBy('name')->get();

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
            'selectedRegionId' => $selectedRegionId,
            'products' => $products,
            'slots' => $slots,
            'rowState' => $rowState,
            'name' => $name,
        ]);
    }

    /**
     * Paso de revisión: valida todo el conjunto mediante StoreArchitectureRequest
     * y guarda el borrador validado en la sesión.
     */
    public function review(StoreArchitectureRequest $request, Project $project): RedirectResponse
    {
        $this->authorize('create', [Architecture::class, $project]);

        $region = Region::query()->findOrFail((int) $request->input('region_id'));
        $validation = $this->architectureValidator->validate($region, $request->input('items', []));

        $draft = [
            'project_id' => $project->id,
            'region_id' => $region->id,
            'name' => (string) $request->input('name'),
            'items' => $validation['items'],
        ];

        $request->session()->put(self::DRAFT_KEY, $draft);

        return redirect()->route('projects.architectures.check', $project);
    }

    /**
     * Alias de review para compatibilidad de rutas y llamadas internas.
     */
    public function preview(StoreArchitectureRequest $request, Project $project): RedirectResponse
    {
        return $this->review($request, $project);
    }

    /**
     * Previsualización del borrador validado: nombre, región, productos,
     * precios y cada elemento antes de guardar.
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
            ->with(['provider', 'availabilities'])
            ->whereIn('id', array_column($draft['items'], 'product_id'))
            ->get()
            ->keyBy('id');

        $pricing = null;
        $pricingError = null;

        try {
            $pricing = $this->pricingService->calculate($region, $draft['items']);
        } catch (PricingException $exception) {
            $pricingError = $exception->getMessage();
        }

        return view('architectures.preview', [
            'project' => $project,
            'draft' => $draft,
            'region' => $region,
            'products' => $products,
            'pricing' => $pricing,
            'pricingError' => $pricingError,
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
                ->withErrors($result['errors']);
        }

        try {
            $this->pricingService->calculate($region, $result['items']);
        } catch (PricingException $exception) {
            return redirect()
                ->route('projects.architectures.create', $project)
                ->withErrors(['items' => $exception->getMessage()]);
        }

        /** @var Architecture $architecture */
        $architecture = DB::transaction(function () use ($project, $draft, $result): Architecture {
            $architecture = Architecture::query()->create([
                'project_id' => $project->id,
                'region_id' => $draft['region_id'],
                'name' => $draft['name'],
                'source' => Architecture::SOURCE_MANUAL,
            ]);

            foreach ($result['items'] as $item) {
                ArchitectureItem::query()->create([
                    'architecture_id' => $architecture->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'config' => $item['config'],
                ]);
            }

            return $architecture;
        });

        $request->session()->forget(self::DRAFT_KEY);

        return redirect()
            ->route('architectures.show', $architecture)
            ->with('success', 'Arquitectura manual guardada correctamente.');
    }

    /**
     * Listado de arquitecturas asociadas a un proyecto.
     */
    public function index(Project $project): View
    {
        $this->authorize('view', $project);

        $architectures = $project->architectures()
            ->with(['region', 'items.product.provider'])
            ->withCount('items')
            ->latest()
            ->get();

        return view('architectures.index', [
            'project' => $project,
            'architectures' => $architectures,
        ]);
    }

    /**
     * Ficha detallada de una arquitectura y sus elementos.
     */
    public function show(Architecture $architecture): View
    {
        $this->authorize('view', $architecture);

        $architecture->load(['region', 'items.product.provider', 'items.product.availabilities', 'project']);

        $pricing = null;
        $pricingError = null;

        try {
            $pricing = $this->pricingService->calculateForArchitecture($architecture);
        } catch (PricingException $exception) {
            $pricingError = $exception->getMessage();
        }

        return view('architectures.show', [
            'architecture' => $architecture,
            'pricing' => $pricing,
            'pricingError' => $pricingError,
        ]);
    }

    /**
     * Productos activos y disponibles en la región dada (RN05), en una
     * única consulta con la disponibilidad comprobada frente a la BD.
     *
     * @return Collection<int, Product>
     */
    private function productsFor(?Region $region): Collection
    {
        if ($region === null) {
            return collect();
        }

        return Product::query()
            ->availableIn($region)
            ->with(['provider', 'availabilities'])
            ->orderBy('name')
            ->get();
    }
}

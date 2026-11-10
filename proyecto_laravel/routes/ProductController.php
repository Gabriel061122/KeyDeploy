<?php

use App\Http\Controllers\ArchitectureController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CartItemController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectFileController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\RequirementController;
use Illuminate\Support\Facades\Route;

public function index(CatalogFilterRequest $request)
{
    $f = $request->validated();
    $products = Product::query()
        ->with(['provider', 'availabilities.region'])           // eager loading
        ->where('active', true)
        ->when($f['type'] ?? null, fn ($q, $v) => $q->where('type', $v))
        ->when($f['provider_id'] ?? null, fn ($q, $v) => $q->where('provider_id', $v))
        ->when($f['q'] ?? null, fn ($q, $v) => $q->where('name', 'like', "%$v%"))
        ->when($f['region_id'] ?? null, fn ($q, $v) => $q->whereHas('availabilities',
            fn ($a) => $a->where('region_id', $v)->where('available', true)))
        ->orderBy('name')->paginate(9)->withQueryString();

    return view('products.index', [
        'products' => $products,
        'types' => ProductType::cases(),
        'providers' => Provider::orderBy('name')->get(),
        'regions' => Region::orderBy('name')->get(),
        'filters' => $f,
    ]);
}

public function show(Product $product)
{
    $product->load(['provider', 'availabilities.region']);
    return view('products.show', compact('product'));
}

// Formulario dinámico sin JS: elegir tipo -> GET create?type=redes recarga con los campos de ese tipo
public function create(Request $request)
{
    return view('products.form', [
        'product' => new Product(),
        'type' => ProductType::tryFrom((string) $request->query('type')),
        'providers' => Provider::orderBy('name')->get(),
        'regions' => Region::orderBy('name')->get(),
    ]);
}

public function store(ProductRequest $request)
{
    $product = DB::transaction(function () use ($request) {
        $product = Product::create($request->safe()->except('prices') + ['active' => $request->boolean('active', true)]);
        $this->syncAvailability($product, $request->input('prices', []));
        return $product;
    });
    return redirect()->route('products.show', $product)->with('status', 'Producto creado.');
}

public function edit(Product $product)
{
    return view('products.form', [
        'product' => $product->load('availabilities'),
        'type' => $product->type,
        'providers' => Provider::orderBy('name')->get(),
        'regions' => Region::orderBy('name')->get(),
    ]);
}

public function update(ProductRequest $request, Product $product)
{
    DB::transaction(function () use ($request, $product) {
        $product->update($request->safe()->except('prices') + ['active' => $request->boolean('active')]);
        $this->syncAvailability($product, $request->input('prices', []));
    });
    return redirect()->route('products.show', $product)->with('status', 'Producto actualizado.');
}

// "Eliminar" = desactivar, para no romper arquitecturas, carritos ni presupuestos de P2/P4
public function destroy(Product $product)
{
    $product->update(['active' => false]);
    return redirect()->route('products.index')->with('status', 'Producto desactivado.');
}

private function syncAvailability(Product $product, array $prices): void
{
    $valid = Region::whereIn('id', array_keys($prices))->pluck('id');
    foreach (Region::pluck('id') as $regionId) {
        $price = $valid->contains($regionId) ? ($prices[$regionId] ?? null) : null;
        if ($price === null || $price === '') {
            $product->availabilities()->where('region_id', $regionId)->update(['available' => false]);
            continue;
        }
        $product->availabilities()->updateOrCreate(
            ['region_id' => $regionId],
            ['available' => true, 'price_cents' => (int) $price, 'pricing_model' => $product->type->defaultPricingModel()]
        );
    }
}
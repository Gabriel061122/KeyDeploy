<?php

namespace App\Http\Controllers;

use App\Enums\ProductType;
use App\Http\Requests\CatalogFilterRequest;
use App\Http\Requests\ProductRequest;
use App\Models\Product;
use App\Models\Provider;
use App\Models\Region;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function index(CatalogFilterRequest $request): View
    {
        $filters = $request->validated();
        $products = Product::query()
            ->with(['provider', 'availabilities.region'])
            ->where('active', true)
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->when($filters['provider_id'] ?? null, fn ($query, $providerId) => $query->where('provider_id', $providerId))
            ->when($filters['q'] ?? null, fn ($query, $term) => $query->where('name', 'like', '%'.$term.'%'))
            ->when($filters['region_id'] ?? null, fn ($query, $regionId) => $query->whereHas(
                'availabilities',
                fn ($availabilityQuery) => $availabilityQuery
                    ->where('region_id', $regionId)
                    ->where('available', true),
            ))
            ->orderBy('name')
            ->paginate(9)
            ->withQueryString();

        return view('products.index', [
            'products' => $products,
            'types' => ProductType::cases(),
            'providers' => Provider::query()->orderBy('name')->get(),
            'regions' => Region::query()->orderBy('name')->get(),
            'filters' => $filters,
        ]);
    }

    public function show(Product $product): View
    {
        abort_unless($product->active, 404);
        $product->load(['provider', 'availabilities.region']);

        return view('products.show', compact('product'));
    }

    public function create(): View
    {
        return view('products.form', [
            'product' => new Product(),
            'types' => ProductType::cases(),
            'providers' => Provider::query()->orderBy('name')->get(),
            'regions' => Region::query()->orderBy('name')->get(),
        ]);
    }

    public function store(ProductRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $product = DB::transaction(function () use ($validated): Product {
            $product = Product::query()->create(collect($validated)->except('prices')->all());
            $this->syncAvailability($product, $validated['prices'] ?? []);

            return $product;
        });

        return redirect()->route('products.show', $product)->with('success', 'Producto creado.');
    }

    public function edit(Product $product): View
    {
        return view('products.form', [
            'product' => $product->load('availabilities'),
            'types' => ProductType::cases(),
            'providers' => Provider::query()->orderBy('name')->get(),
            'regions' => Region::query()->orderBy('name')->get(),
        ]);
    }

    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        $validated = $request->validated();
        DB::transaction(function () use ($validated, $product): void {
            $product->update(collect($validated)->except('prices')->all());
            $this->syncAvailability($product, $validated['prices'] ?? []);
        });

        return redirect()->route('products.show', $product)->with('success', 'Producto actualizado.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $product->update(['active' => false]);

        return redirect()->route('products.index')->with('success', 'Producto desactivado.');
    }

    /** @param array<int|string, mixed> $prices */
    private function syncAvailability(Product $product, array $prices): void
    {
        foreach (Region::query()->pluck('id') as $regionId) {
            $price = $prices[$regionId] ?? null;
            $isAvailable = $price !== null && $price !== '';

            $product->availabilities()->updateOrCreate(
                ['region_id' => $regionId],
                [
                    'available' => $isAvailable,
                    'price_cents' => $isAvailable ? (int) $price : 0,
                    'pricing_model' => $product->type->defaultPricingModel(),
                ],
            );
        }
    }
}

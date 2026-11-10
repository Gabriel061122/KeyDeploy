@props(['product'])

<article class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
    <h2 class="font-semibold text-lg"><a href="{{ route('products.show', $product) }}">{{ $product->name }}</a></h2>
    <p class="text-sm text-slate-500">{{ $product->type->label() }} · {{ $product->provider?->name ?? 'Sin proveedor' }}</p>
    <p class="mt-3 text-sm">{{ $product->description }}</p>
    <p class="mt-3 text-sm">
        <span class="font-semibold">Regiones disponibles:</span>
        @forelse ($product->availabilities->where('available', true) as $availability)
            <span class="inline-block bg-indigo-50 text-indigo-800 rounded px-2 py-1 text-xs">{{ $availability->region->code }}</span>
        @empty
            <em>Sin disponibilidad</em>
        @endforelse
    </p>
</article>

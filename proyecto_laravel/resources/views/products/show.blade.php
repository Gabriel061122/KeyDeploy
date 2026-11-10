@extends('layouts.app')

@section('title', $product->name.' · Catálogo')

@section('content')
    <p class="mb-4"><a href="{{ route('products.index') }}">← Catálogo</a></p>
    <section class="card">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-sm text-indigo-700 font-semibold">{{ $product->type->label() }} · {{ $product->provider?->name ?? 'Sin proveedor' }}</p>
                <h1 class="mt-2">{{ $product->name }}</h1>
                <p class="text-slate-600">{{ $product->description }}</p>
            </div>
            @auth
                <a class="btn btn-secondary" href="{{ route('products.edit', $product) }}">Editar</a>
                <form method="POST" action="{{ route('products.destroy', $product) }}">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-secondary" type="submit">Desactivar</button>
                </form>
            @endauth
        </div>
        <dl class="grid gap-3 mt-6 sm:grid-cols-2">
            <div><dt class="font-semibold">Unidad de facturación</dt><dd>{{ $product->billing_unit }}</dd></div>
            <div><dt class="font-semibold">Configuración</dt><dd><pre class="whitespace-pre-wrap">{{ json_encode($product->config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre></dd></div>
        </dl>
    </section>

    <section class="card">
        <h2>Disponibilidad y precio por región</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead><tr><th class="p-2">Región</th><th class="p-2">Disponibilidad</th><th class="p-2">Precio</th><th class="p-2">Tarifa</th></tr></thead>
                <tbody>
                    @forelse ($product->availabilities as $availability)
                        <tr class="border-t">
                            <td class="p-2">{{ $availability->region->name }}</td>
                            <td class="p-2">{{ $availability->available ? 'Disponible' : 'No disponible' }}</td>
                            <td class="p-2">{{ number_format($availability->price_cents / 100, 2, ',', '.') }} €</td>
                            <td class="p-2">{{ $availability->pricing_model->label() }}</td>
                        </tr>
                    @empty
                        <tr><td class="p-2" colspan="4">Sin disponibilidad configurada.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection

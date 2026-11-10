@extends('layouts.app')

@section('title', 'Catálogo de productos')

@section('content')
    <div class="flex items-center justify-between gap-4 mb-6">
        <div>
            <h1>Catálogo de productos</h1>
            <p class="text-slate-600">Servicios disponibles por proveedor y región.</p>
        </div>
        @auth
            <a class="btn" href="{{ route('products.create') }}">Nuevo producto</a>
        @endauth
    </div>

    <form method="GET" action="{{ route('products.index') }}" class="card grid gap-4 md:grid-cols-5 items-end">
        <label>Buscar
            <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="60">
        </label>
        <label>Tipo
            <select name="type">
                <option value="">Todos</option>
                @foreach ($types as $type)
                    <option value="{{ $type->value }}" @selected(($filters['type'] ?? '') === $type->value)>{{ $type->label() }}</option>
                @endforeach
            </select>
        </label>
        <label>Proveedor
            <select name="provider_id">
                <option value="">Todos</option>
                @foreach ($providers as $provider)
                    <option value="{{ $provider->id }}" @selected((string) ($filters['provider_id'] ?? '') === (string) $provider->id)>{{ $provider->name }}</option>
                @endforeach
            </select>
        </label>
        <label>Región disponible
            <select name="region_id">
                <option value="">Todas</option>
                @foreach ($regions as $region)
                    <option value="{{ $region->id }}" @selected((string) ($filters['region_id'] ?? '') === (string) $region->id)>{{ $region->name }}</option>
                @endforeach
            </select>
        </label>
        <div class="flex gap-2">
            <button class="btn" type="submit">Filtrar</button>
            <a class="btn btn-secondary" href="{{ route('products.index') }}">Limpiar</a>
        </div>
    </form>

    @if ($products->isEmpty())
        <div class="card text-slate-600">No hay productos que coincidan con los filtros.</div>
    @else
        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            @foreach ($products as $product)
                <x-product-card :product="$product" />
            @endforeach
        </div>
        <div class="mt-6">{{ $products->links() }}</div>
    @endif
@endsection

@extends('layouts.app')

@php($isEditing = $product->exists)
@section('title', $isEditing ? 'Editar producto' : 'Nuevo producto')

@section('content')
    <h1>{{ $isEditing ? 'Editar producto' : 'Crear producto' }}</h1>
    <form class="card space-y-4" method="POST" action="{{ $isEditing ? route('products.update', $product) : route('products.store') }}">
        @csrf
        @if ($isEditing) @method('PUT') @endif

        <label>Nombre
            <input name="name" required maxlength="100" value="{{ old('name', $product->name) }}">
        </label>
        <label>Descripción
            <textarea name="description" maxlength="500" rows="3">{{ old('description', $product->description) }}</textarea>
        </label>
        <div class="grid gap-4 md:grid-cols-3">
            <label>Proveedor
                <select name="provider_id" required>
                    <option value="">Selecciona</option>
                    @foreach ($providers as $provider)
                        <option value="{{ $provider->id }}" @selected((string) old('provider_id', $product->provider_id) === (string) $provider->id)>{{ $provider->name }}</option>
                    @endforeach
                </select>
            </label>
            <label>Tipo
                <select name="type" required>
                    <option value="">Selecciona</option>
                    @foreach ($types as $type)
                        <option value="{{ $type->value }}" @selected(old('type', $product->type?->value) === $type->value)>{{ $type->label() }}</option>
                    @endforeach
                </select>
            </label>
            <label>Unidad de facturación
                <input name="billing_unit" required maxlength="20" value="{{ old('billing_unit', $product->billing_unit ?? 'mes') }}">
            </label>
        </div>

        <label>Configuración técnica (JSON)
            <textarea name="config" rows="6" required>{{ is_array(old('config')) ? json_encode(old('config'), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : old('config', json_encode($product->config ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) }}</textarea>
            <span class="block text-sm text-slate-500">Introduce los campos requeridos para el tipo seleccionado, por ejemplo: {"vcpu": 2, "ram_gb": 4}.</span>
        </label>

        <fieldset>
            <legend class="font-semibold mb-2">Precios en céntimos por región (deja vacío si no está disponible)</legend>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($regions as $region)
                    @php($availability = $product->availabilities->firstWhere('region_id', $region->id))
                    <label>{{ $region->name }}
                        <input type="number" min="0" step="1" name="prices[{{ $region->id }}]" value="{{ old('prices.'.$region->id, $availability?->available ? $availability->price_cents : '') }}" placeholder="No disponible">
                    </label>
                @endforeach
            </div>
        </fieldset>

        @if ($isEditing)
            <input type="hidden" name="active" value="0">
            <label class="flex items-center gap-2"><input type="checkbox" name="active" value="1" @checked(old('active', $product->active))> Producto activo</label>
        @endif
        <div class="flex gap-3">
            <button class="btn" type="submit">{{ $isEditing ? 'Guardar cambios' : 'Crear producto' }}</button>
            <a class="btn btn-secondary" href="{{ $isEditing ? route('products.show', $product) : route('products.index') }}">Cancelar</a>
        </div>
    </form>
@endsection

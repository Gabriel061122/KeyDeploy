@extends('layouts.app')

@section('title', 'Nueva arquitectura — '.$project->name)

@section('content')
<h1>Crear arquitectura manual en «{{ $project->name }}»</h1>

{{--
    Formulario único sin JavaScript. El botón «Actualizar» envía el mismo
    formulario a la acción de recarga (POST) sin validación del navegador:
    el servidor conserva todo lo tecleado y vuelve a mostrar el catálogo
    disponible en la región elegida (RN05). El envío principal solo
    revisa y previsualiza la arquitectura.
--}}
<form method="POST" action="{{ route('projects.architectures.review', $project) }}" class="card">
    @csrf
    <h2>Datos de la arquitectura</h2>

    <label for="name">Nombre de la arquitectura</label>
    <input id="name" type="text" name="name" value="{{ $name }}" maxlength="150" required>
    @error('name') <p class="field-error">{{ $message }}</p> @enderror

    <label for="region_id">Región de la arquitectura</label>
    <div style="display: flex; gap: 0.5rem; align-items: stretch;">
        <select id="region_id" name="region_id">
            <option value="">— Elige una región —</option>
            @foreach ($regions as $region)
                <option value="{{ $region->id }}" @selected((int) $region->id === $selectedRegionId)>{{ $region->name }} ({{ $region->code }}){{ $region->is_eu ? ' [UE]' : '' }}</option>
            @endforeach
        </select>
        <button type="submit" formaction="{{ route('projects.architectures.refresh', $project) }}" formnovalidate class="btn btn-secondary">Actualizar</button>
    </div>
    @error('region_id') <p class="field-error">{{ $message }}</p> @enderror
    <p class="muted">Al actualizar se muestra el catálogo disponible en la región elegida conservando el nombre, los productos, las cantidades y los consumos ya indicados. Los productos retirados o no disponibles no podrán usarse (RN05).</p>

    @if (! $selectedRegionId)
        <p class="field-error">Elige la región y pulsa «Actualizar» para cargar los productos disponibles.</p>
    @elseif ($products->isEmpty())
        <p class="field-error">No hay productos disponibles en la región seleccionada. Elige otra región y pulsa «Actualizar».</p>
    @endif

    <h2 style="margin-top: 1.5rem;">Elementos ({{ $slots }} huecos; se exigen al menos 3 y 2 tipos distintos)</h2>
    <p class="muted">El consumo mensual es opcional en las tarifas fijas. En las tarifas por hora se indica en horas y en las tarifas por GB en GB; en esos casos es obligatorio y debe ser mayor que cero.</p>

    @for ($i = 0; $i < $slots; $i++)
        <fieldset class="slot">
            <legend>Elemento {{ $i + 1 }}</legend>
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 0.75rem;">
                <div>
                    <label for="product_id_{{ $i }}">Producto del catálogo</label>
                    <select id="product_id_{{ $i }}" name="items[{{ $i }}][product_id]">
                        <option value="">— sin producto —</option>
                        @foreach ($products as $product)
                            <option value="{{ $product->id }}" @selected((string) $rowState[$i]['product_id'] === (string) $product->id)>
                                {{ $product->name }} [{{ $product->code }}] ({{ $product->type instanceof \App\Enums\ProductType ? $product->type->label() : $product->type }}) — {{ $product->provider?->name }} — {{ number_format($product->priceInRegion($selectedRegionId) ?? 0, 2) }} €/{{ $product->billing_unit }}
                            </option>
                        @endforeach
                    </select>
                    @error('items.'.$i.'.product_id') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="quantity_{{ $i }}">Cantidad</label>
                    <input id="quantity_{{ $i }}" type="number" name="items[{{ $i }}][quantity]" min="1" step="1" value="{{ $rowState[$i]['quantity'] }}">
                    @error('items.'.$i.'.quantity') <p class="field-error">{{ $message }}</p> @enderror
                </div>
            </div>
            <label for="usage_{{ $i }}">Consumo mensual</label>
            <input id="usage_{{ $i }}" type="number" name="items[{{ $i }}][usage]" min="0" step="0.01" value="{{ $rowState[$i]['usage'] }}">
            <p class="muted">Horas al mes para tarifas por hora, GB al mes para tarifas por GB. Se ignora en las tarifas fijas.</p>
            @error('items.'.$i.'.usage') <p class="field-error">{{ $message }}</p> @enderror
        </fieldset>
    @endfor

    <div class="actions">
        @if ($products->isNotEmpty())
            <button type="submit" class="btn">Revisar y previsualizar</button>
        @endif
        @if (session()->has(\App\Http\Controllers\ArchitectureController::DRAFT_KEY))
            <a class="btn btn-secondary" href="{{ route('projects.architectures.check', $project) }}">Ir a la revisión pendiente</a>
        @endif
        <a class="btn btn-secondary" href="{{ route('projects.show', $project) }}">Cancelar</a>
    </div>
</form>
@endsection

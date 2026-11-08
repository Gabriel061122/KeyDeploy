@extends('layouts.app')

@section('title', 'Nueva arquitectura — '.$project->name)

@section('content')
<h1>Crear arquitectura manual en «{{ $project->name }}»</h1>

{{--
    Formulario 1 (GET): región. Recargar la página para ajustar el
    listado de productos disponibles en la región (RN05) conservando
    todos los datos ya introducidos mediante campos ocultos.
--}}
<form method="GET" action="{{ route('projects.architectures.create', $project) }}" class="card">
    <input type="hidden" name="name" value="{{ $name }}">
    @for ($i = 0; $i < $slots; $i++)
        <input type="hidden" name="items[{{ $i }}][product_id]" value="{{ $rowState[$i]['product_id'] }}">
        <input type="hidden" name="items[{{ $i }}][quantity]" value="{{ $rowState[$i]['quantity'] }}">
        <input type="hidden" name="items[{{ $i }}][config]" value="{{ $rowState[$i]['config'] }}">
    @endfor
    <label for="region_id">Región de la arquitectura</label>
    <div style="display: flex; gap: 0.5rem; align-items: stretch;">
        <select id="region_id" name="region_id">
            <option value="">— Elige una región —</option>
            @foreach ($regions as $region)
                <option value="{{ $region->id }}" @selected((int) $region->id === $selectedRegionId)>{{ $region->name }} ({{ $region->code }})</option>
            @endforeach
        </select>
        <button type="submit" class="btn">Actualizar</button>
    </div>
    @error('region_id') <p class="field-error">{{ $message }}</p> @enderror
    <p class="muted">Al actualizar se muestra el catálogo disponible en la región elegida: productos retirados o no disponibles no podrán usarse.</p>
</form>

{{--
    Formulario 2 (POST): envía el borrador a la revisión/previsualización.
    Cada elemento necesita producto, cantidad entera >= 1 y configuración
    JSON válida; el servidor exige al menos 3 elementos y 2 tipos.
--}}
<form method="POST" action="{{ route('projects.architectures.review', $project) }}" class="card">
    @csrf
    <h2>Datos de la arquitectura</h2>

    <label for="name">Nombre de la arquitectura</label>
    <input id="name" type="text" name="name" value="{{ $name }}" maxlength="150" required>
    @error('name') <p class="field-error">{{ $message }}</p> @enderror

    <label>Región (paso 1)</label>
    <input type="hidden" name="region_id" value="{{ $selectedRegionId }}">
    <input type="text" value="{{ $regions->firstWhere('id', $selectedRegionId)?->name ?? '— no seleccionada —' }}" disabled>
    @if (! $selectedRegionId)
        <p class="field-error">Elige la región en el paso 1 y pulsa «Actualizar».</p>
    @endif

    @if ($products->isEmpty())
        @if ($selectedRegionId)
            <p class="field-error">No hay productos disponibles en la región seleccionada. Elige otra región y pulsa «Actualizar».</p>
        @endif
    @endif

    <h2 style="margin-top: 1.5rem;">Elementos ({{ $slots }} huecos; se exigen al menos 3 y 2 tipos distintos)</h2>
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
                                {{ $product->code }} — {{ $product->name }} ({{ $product->type }})
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
            <label for="config_{{ $i }}">Configuración JSON individual</label>
            <textarea id="config_{{ $i }}" name="items[{{ $i }}][config]" placeholder='{"instancia": "t3.medium", "disco_gb": 100}'>{{ $rowState[$i]['config'] }}</textarea>
            @error('items.'.$i.'.config') <p class="field-error">{{ $message }}</p> @enderror
        </fieldset>
    @endfor

    <div class="actions">
        <button type="submit" class="btn">Revisar y previsualizar</button>
        @if (session()->has(\App\Http\Controllers\ArchitectureController::DRAFT_KEY))
            <a class="btn btn-secondary" href="{{ route('projects.architectures.check', $project) }}">Ir a la revisión pendiente</a>
        @endif
        <a class="btn btn-secondary" href="{{ route('projects.show', $project) }}">Cancelar</a>
    </div>
</form>
@endsection

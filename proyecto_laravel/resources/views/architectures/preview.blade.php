@extends('layouts.app')

@section('title', 'Revisar arquitectura — '.$project->name)

@section('content')
<h1>Revisar arquitectura antes de guardar</h1>

<div class="card">
    <h3>Datos generales</h3>
    <dl class="detail">
        <dt>Proyecto</dt><dd><a href="{{ route('projects.show', $project) }}">{{ $project->name }}</a></dd>
        <dt>Nombre</dt><dd>{{ $draft['name'] }}</dd>
        <dt>Región</dt><dd>{{ $region->name }} <span class="muted">({{ $region->code }})</span></dd>
        <dt>Origen</dt><dd><span class="badge">manual</span></dd>
    </dl>
</div>

<div class="card">
    <h3>Elementos ({{ count($draft['items']) }})</h3>
    @if (count($draft['items']) < 3)
        <p class="field-error">La arquitectura necesita al menos 3 elementos; no podrá guardarse.</p>
    @endif
    <table>
        <thead>
            <tr><th>#</th><th>Producto</th><th>Tipo</th><th>Cantidad</th><th>Configuración</th></tr>
        </thead>
        <tbody>
            @foreach ($draft['items'] as $index => $item)
                @php
                    $product = $products->get($item['product_id']);
                @endphp
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $product?->code }} — {{ $product?->name }}</td>
                    <td>{{ $product?->type }}</td>
                    <td>{{ $item['quantity'] }}</td>
                    <td><pre class="config">{{ json_encode($item['config'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre></td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <p class="muted">Comprobaciones aplicadas: elementos y tipos mínimos, productos existentes y activos y 
    disponibilidad en la región (RN05). El guardado final revalida todo de nuevo.</p>
</div>

<div class="actions">
    <form method="POST" action="{{ route('projects.architectures.store', $project) }}">
        @csrf
        <button type="submit" class="btn">Confirmar y guardar arquitectura</button>
    </form>
    <a class="btn btn-secondary" href="{{ route('projects.architectures.create', $project) }}">Volver al formulario</a>
</div>
@endsection

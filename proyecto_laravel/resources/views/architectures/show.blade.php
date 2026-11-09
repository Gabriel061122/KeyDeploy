@extends('layouts.app')

@section('title', $architecture->name.' — Arquitectura manual')

@section('content')
<div class="toolbar">
    <h1 style="margin: 0;">{{ $architecture->name }}</h1>
    <a class="btn btn-secondary" href="{{ route('projects.show', $architecture->project) }}">Volver al proyecto</a>
</div>

<div class="card">
    <h2>Datos de la arquitectura</h2>
    <dl class="detail">
        <dt>Proyecto</dt><dd><a href="{{ route('projects.show', $architecture->project) }}">{{ $architecture->project->name }}</a></dd>
        <dt>Región asignada</dt><dd>{{ $architecture->region->name }} <span class="muted">({{ $architecture->region->code }}){{ $architecture->region->is_eu ? ' [UE]' : '' }}</span></dd>
        <dt>Origen de la arquitectura</dt><dd><span class="badge">{{ $architecture->source }}</span></dd>
        <dt>Fecha de diseño</dt><dd>{{ $architecture->created_at->format('d/m/Y H:i') }}</dd>
    </dl>
</div>

<div class="card">
    <h2>Elementos y configuración ({{ $architecture->items->count() }})</h2>
    <table>
        <thead>
            <tr><th>#</th><th>Producto</th><th>Proveedor</th><th>Tipo</th><th>Cantidad</th><th>Tarifa regional</th><th>Configuración</th></tr>
        </thead>
        <tbody>
            @foreach ($architecture->items as $index => $item)
                @php
                    $price = $item->product->priceInRegion($architecture->region);
                @endphp
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td><strong>{{ $item->product->name }}</strong> <code class="inline">{{ $item->product->code }}</code></td>
                    <td>{{ $item->product->provider?->name ?? '—' }}</td>
                    <td><span class="badge">{{ $item->product->type instanceof \App\Enums\ProductType ? $item->product->type->label() : $item->product->type }}</span></td>
                    <td>{{ $item->quantity }}</td>
                    <td>{{ $price !== null ? number_format($price, 2).' € / '.$item->product->billing_unit : '—' }}</td>
                    <td><pre class="config">{{ json_encode($item->config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre></td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection

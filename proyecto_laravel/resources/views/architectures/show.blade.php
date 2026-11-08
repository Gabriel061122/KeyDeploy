@extends('layouts.app')

@section('title', $architecture->name.' — KeyDeploy')

@section('content')
<div class="toolbar">
    <h1 style="margin: 0;">{{ $architecture->name }}</h1>
    <div class="actions" style="margin: 0;">
        <a class="btn btn-secondary" href="{{ route('projects.architectures.create', $architecture->project) }}">Crear otra arquitectura</a>
    </div>
</div>

<div class="card">
    <dl class="detail">
        <dt>Proyecto</dt><dd><a href="{{ route('projects.show', $architecture->project) }}">{{ $architecture->project->name }}</a></dd>
        <dt>Región</dt><dd>{{ $architecture->region->name }} <span class="muted">({{ $architecture->region->code }})</span></dd>
        <dt>Origen</dt><dd><span class="badge">{{ $architecture->source }}</span></dd>
        <dt>Creada</dt><dd class="muted">{{ $architecture->created_at?->format('d/m/Y H:i') }}</dd>
    </dl>
</div>

<div class="card">
    <h3>Elementos ({{ $architecture->items->count() }})</h3>
    <table>
        <thead>
            <tr><th>#</th><th>Producto</th><th>Tipo</th><th>Cantidad</th><th>Configuración</th></tr>
        </thead>
        <tbody>
            @foreach ($architecture->items as $index => $item)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $item->product->code }} — {{ $item->product->name }}</td>
                    <td>{{ $item->product->type }}</td>
                    <td>{{ $item->quantity }}</td>
                    <td><pre class="config">{{ json_encode($item->config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre></td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

<div class="actions">
    <a class="btn btn-secondary" href="{{ route('projects.architectures.index', $architecture->project) }}">← Arquitecturas del proyecto</a>
</div>
@endsection

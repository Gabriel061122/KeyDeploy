@extends('layouts.app')

@section('title', $project->name.' — KeyDeploy')

@section('content')
<div class="toolbar">
    <h1 style="margin: 0;">{{ $project->name }}</h1>
    <div class="actions" style="margin: 0;">
        <a class="btn btn-secondary" href="{{ route('projects.edit', $project) }}">Editar proyecto</a>
        <a class="btn btn-danger" href="{{ route('projects.delete', $project) }}">Eliminar</a>
    </div>
</div>

<div class="card">
    <h2>Datos del proyecto</h2>
    <dl class="detail">
        <dt>Descripción funcional</dt><dd>{{ $project->description }}</dd>
        <dt>Región</dt><dd>{{ $project->region->name }} <span class="muted">({{ $project->region->code }})</span></dd>
        <dt>Presupuesto máximo</dt><dd>{{ number_format((float) $project->max_budget, 2) }} €</dd>
        <dt>Usuarios esperados</dt><dd>{{ number_format($project->expected_users) }}</dd>
    </dl>
</div>

<div class="card">
    <div class="toolbar">
        <h2 style="margin: 0;">Requerimientos ({{ $requirements->count() }})</h2>
        <div class="actions" style="margin: 0;">
            <a class="btn" href="{{ route('projects.requirements.create', $project) }}">Nuevo requerimiento</a>
            <a class="btn btn-secondary" href="{{ route('projects.requirements.index', $project) }}">Lista completa</a>
        </div>
    </div>
    @if ($requirements->isEmpty())
        <p class="empty">Sin requerimientos estructurados todavía. Añade el primero con el formulario dinámico.</p>
    @else
        <table>
            <thead>
                <tr><th>Campo</th><th>Condición</th><th>Obligatoriedad</th><th>Origen</th></tr>
            </thead>
            <tbody>
                @foreach ($requirements as $requirement)
                    <tr>
                        <td><code class="inline">{{ $requirement->field_key }}</code></td>
                        <td>{{ $requirement->operator }} («{{ $requirement->value }}»)</td>
                        <td>{{ $requirement->mandatory ? 'Obligatorio' : 'Opcional' }}</td>
                        <td><span class="badge">{{ $requirement->source }}</span></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

<div class="card">
    <div class="toolbar">
        <h2 style="margin: 0;">Arquitecturas ({{ $architectures->count() }})</h2>
        <div class="actions" style="margin: 0;">
            <a class="btn" href="{{ route('projects.architectures.create', $project) }}">Crear arquitectura manual</a>
            <a class="btn btn-secondary" href="{{ route('projects.architectures.index', $project) }}">Lista completa</a>
        </div>
    </div>
    @if ($architectures->isEmpty())
        <p class="empty">Aún no hay arquitecturas para este proyecto.</p>
    @else
        <table>
            <thead>
                <tr><th>Nombre</th><th>Región</th><th>Elementos</th><th>Origen</th></tr>
            </thead>
            <tbody>
                @foreach ($architectures as $architecture)
                    <tr>
                        <td><a href="{{ route('architectures.show', $architecture) }}">{{ $architecture->name }}</a></td>
                        <td>{{ $architecture->region->name }}</td>
                        <td>{{ $architecture->items->count() }}</td>
                        <td><span class="badge">{{ $architecture->source }}</span></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

<div class="actions">
    <a class="btn btn-secondary" href="{{ route('projects.index') }}">← Todos los proyectos</a>
</div>
@endsection

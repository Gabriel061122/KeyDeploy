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

<!-- Ficheros adjuntos -->
<div class="card">
    <div class="toolbar">
        <h2 style="margin:0;">Ficheros adjuntos</h2>
    </div>

    <!-- Formulario de subida -->
    <form method="POST" action="{{ route('files.upload', $project) }}" enctype="multipart/form-data" class="mb-4">
        @csrf
        <div class="flex gap-2 items-center">
            <input type="file" name="file" accept=".pdf,.png,.jpg,.jpeg,.docx,.md" required>
            <button type="submit" class="btn btn-sm">Subir</button>
        </div>
        <p class="muted" style="font-size:0.75rem;margin-top:0.25rem">PDF, PNG, JPG, DOCX, MD — máx 10 MB</p>
    </form>

    @if(isset($files) && $files->isNotEmpty())
        <table>
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Tipo</th>
                    <th>Tamaño</th>
                    <th>Subido</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($files as $file)
                    <tr>
                        <td>
                            <a href="{{ route('files.show', $file) }}" target="_blank">{{ $file->original_name }}</a>
                        </td>
                        <td><span class="badge">{{ pathinfo($file->original_name, PATHINFO_EXTENSION) }}</span></td>
                        <td>{{ number_format($file->size_bytes / 1024, 1) }} KB</td>
                        <td>{{ $file->created_at->format('d/m/Y H:i') }}</td>
                        <td class="flex gap-2">
                            <!-- Sustituir -->
                            <form method="POST" action="{{ route('files.replace', $file) }}" enctype="multipart/form-data" class="inline-flex gap-1">
                                @csrf
                                <input type="file" name="file" accept=".pdf,.png,.jpg,.jpeg,.docx,.md" required
                                       style="font-size:0.7rem">
                                <button type="submit" class="btn btn-sm btn-secondary">Sustituir</button>
                            </form>
                            <!-- Eliminar -->
                            <form method="POST" action="{{ route('files.destroy', $file) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-danger" title="Eliminar">✕</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p class="empty">No hay ficheros adjuntos.</p>
    @endif
</div>

<!-- Presupuestos emitidos -->
@if(isset($quotes) && $quotes->isNotEmpty())
<div class="card">
    <div class="toolbar">
        <h2 style="margin:0;">Presupuestos emitidos</h2>
    </div>
    <table>
        <thead>
            <tr><th>Nº</th><th>Fecha</th><th>Total</th><th></th></tr>
        </thead>
        <tbody>
            @foreach ($quotes as $q)
                <tr>
                    <td>#{{ $q->id }}</td>
                    <td>{{ $q->issued_at->format('d/m/Y H:i') }}</td>
                    <td>{{ number_format($q->total_cents / 100, 2) }} €</td>
                    <td><a href="{{ route('quotes.show', $q) }}" class="btn btn-sm btn-secondary">Ver</a></td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

<!-- Emitir presupuesto desde carrito -->
<div class="card">
    <h2>Emitir presupuesto</h2>
    <p class="muted">Genera un presupuesto congelando las líneas del carrito actual.</p>
    <form method="POST" action="{{ route('quotes.emit', $project) }}" class="mt-3">
        @csrf
        <button type="submit" class="btn">Emitir presupuesto desde el carrito</button>
    </form>
</div>

<div class="actions">
    <a class="btn btn-secondary" href="{{ route('projects.index') }}">← Todos los proyectos</a>
</div>
@endsection

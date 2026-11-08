@extends('layouts.app')

@section('title', 'Eliminar requerimiento — '.$project->name)

@section('content')
<h1>¿Eliminar este requerimiento?</h1>
<div class="card">
    <dl class="detail">
        <dt>Proyecto</dt><dd>{{ $project->name }}</dd>
        <dt>Campo</dt><dd><code class="inline">{{ $requirement->field_key }}</code></dd>
        <dt>Condición</dt><dd>{{ $requirement->operator }} <code class="inline">{{ $requirement->value }}</code></dd>
        <dt>Observación</dt><dd class="muted">Esta acción es definitiva; el resto del proyecto no se ve afectado.</dd>
    </dl>
    <div class="actions">
        <form method="POST" action="{{ route('projects.requirements.destroy', [$project, $requirement]) }}">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-danger">Sí, eliminar requerimiento</button>
        </form>
        <a href="{{ route('projects.requirements.index', $project) }}" class="btn btn-secondary">No, volver</a>
    </div>
</div>
@endsection

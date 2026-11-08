@extends('layouts.app')

@section('title', 'Requerimiento #'.$requirement->id.' — '.$project->name)

@section('content')
<h1>Requerimiento #{{ $requirement->id }}</h1>
<div class="card">
    <dl class="detail">
        <dt>Proyecto</dt><dd><a href="{{ route('projects.show', $project) }}">{{ $project->name }}</a></dd>
        <dt>Campo</dt><dd><code class="inline">{{ $requirement->field_key }}</code></dd>
        <dt>Tipo de dato</dt><dd>{{ \App\Services\RequirementRules::TYPE_LABELS[$requirement->data_type] ?? $requirement->data_type }} <span class="muted">({{ $requirement->data_type }})</span></dd>
        <dt>Operador</dt><dd>{{ $operatorLabels[$requirement->operator] ?? $requirement->operator }} <span class="muted">({{ $requirement->operator }})</span></dd>
        <dt>Valor</dt><dd><code class="inline">{{ $requirement->value }}</code></dd>
        <dt>Obligatoriedad</dt><dd>{{ $requirement->mandatory ? 'Obligatorio' : 'Opcional' }}</dd>
        <dt>Origen</dt><dd><span class="badge">{{ $requirement->source }}</span> <span class="muted">asignado por el servidor</span></dd>
        <dt>Creado</dt><dd class="muted">{{ $requirement->created_at?->format('d/m/Y H:i') }}</dd>
        <dt>Actualizado</dt><dd class="muted">{{ $requirement->updated_at?->format('d/m/Y H:i') }}</dd>
    </dl>
    <div class="actions">
        <a href="{{ route('projects.requirements.edit', [$project, $requirement]) }}" class="btn btn-secondary">Editar</a>
        <a href="{{ route('projects.requirements.delete', [$project, $requirement]) }}" class="btn btn-danger">Eliminar</a>
        <a href="{{ route('projects.requirements.index', $project) }}" class="btn">← Requerimientos</a>
    </div>
</div>
@endsection

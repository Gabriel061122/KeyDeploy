@extends('layouts.app')

@section('title', 'Requerimientos — '.$project->name)

@section('content')
<div class="toolbar">
    <h1 style="margin: 0;">Requerimientos de «{{ $project->name }}»</h1>
    <a class="btn" href="{{ route('projects.requirements.create', $project) }}">Nuevo requerimiento</a>
</div>

@if ($requirements->isEmpty())
    <div class="card"><p class="empty">Este proyecto aún no tiene requerimientos estructurados.</p></div>
@else
    <div class="card">
        <table>
            <thead>
                <tr>
                    <th>#</th><th>Campo</th><th>Tipo</th><th>Operador</th><th>Valor</th><th>Oblig.</th><th>Origen</th><th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($requirements as $requirement)
                    <tr>
                        <td>{{ $requirement->id }}</td>
                        <td><code class="inline">{{ $requirement->field_key }}</code></td>
                        <td>{{ $requirement->data_type }}</td>
                        <td>{{ $requirement->operator }}</td>
                        <td><code class="inline">{{ $requirement->value }}</code></td>
                        <td>{{ $requirement->mandatory ? 'Obligatorio' : 'Opcional' }}</td>
                        <td><span class="badge">{{ $requirement->source }}</span></td>
                        <td>
                            <div class="actions" style="margin: 0;">
                                <a class="btn btn-secondary btn-sm" href="{{ route('projects.requirements.show', [$project, $requirement]) }}">Ver</a>
                                <a class="btn btn-secondary btn-sm" href="{{ route('projects.requirements.edit', [$project, $requirement]) }}">Editar</a>
                                <a class="btn btn-danger btn-sm" href="{{ route('projects.requirements.delete', [$project, $requirement]) }}">Eliminar</a>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

<div class="actions">
    <a class="btn btn-secondary" href="{{ route('projects.show', $project) }}">← Volver al proyecto</a>
</div>
@endsection

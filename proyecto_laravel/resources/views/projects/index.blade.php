@extends('layouts.app')

@section('title', 'Mis proyectos — KeyDeploy')

@section('content')
<div class="toolbar">
    <h1 style="margin: 0;">Mis proyectos</h1>
    <a class="btn" href="{{ route('projects.create') }}">Crear proyecto</a>
</div>

@if ($projects->isEmpty())
    <div class="card">
        <p class="empty">Todavía no tienes proyectos. Crea el primero para describir tu aplicación.</p>
    </div>
@else
    <div class="card">
        <table>
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Región</th>
                    <th>Requerimientos</th>
                    <th>Arquitecturas</th>
                    <th>Presupuesto</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($projects as $project)
                    <tr>
                        <td><a href="{{ route('projects.show', $project) }}">{{ $project->name }}</a></td>
                        <td>{{ $project->region?->name ?? '—' }}</td>
                        <td>{{ $project->requirements_count }}</td>
                        <td>{{ $project->architectures_count }}</td>
                        <td>{{ number_format((float) $project->max_budget, 2) }} €</td>
                        <td>
                            <a class="btn btn-secondary btn-sm" href="{{ route('projects.edit', $project) }}">Editar</a>
                            <a class="btn btn-danger btn-sm" href="{{ route('projects.delete', $project) }}">Eliminar</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

{{-- Paginación por defecto del framework (Tailwind). --}}
{{ $projects->links() }}
@endsection

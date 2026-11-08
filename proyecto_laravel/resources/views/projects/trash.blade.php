@extends('layouts.app')

@section('title', 'Papelera — KeyDeploy')

@section('content')
<div class="toolbar">
    <h1 style="margin: 0;">Papelera de proyectos</h1>
    <a class="btn btn-secondary" href="{{ route('projects.index') }}">Volver a mis proyectos</a>
</div>

@if ($projects->isEmpty())
    <div class="card"><p class="empty">La papelera está vacía.</p></div>
@else
    <div class="card">
        <table>
            <thead>
                <tr><th>Proyecto</th><th>Región</th><th>Eliminado</th><th></th></tr>
            </thead>
            <tbody>
                @foreach ($projects as $project)
                    <tr>
                        <td>{{ $project->name }}</td>
                        <td>{{ $project->region?->name ?? '—' }}</td>
                        <td class="muted">{{ $project->deleted_at?->format('d/m/Y H:i') }}</td>
                        <td>
                            <form method="POST" action="{{ route('projects.restore', $project) }}">
                                @csrf
                                <button type="submit" class="btn btn-secondary btn-sm">Restaurar</button>
                            </form>
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

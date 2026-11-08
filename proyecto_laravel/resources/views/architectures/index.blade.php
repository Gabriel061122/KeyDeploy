@extends('layouts.app')

@section('title', 'Arquitecturas — '.$project->name)

@section('content')
<div class="toolbar">
    <h1 style="margin: 0;">Arquitecturas de «{{ $project->name }}»</h1>
    <a class="btn" href="{{ route('projects.architectures.create', $project) }}">Crear arquitectura manual</a>
</div>

@if ($architectures->isEmpty())
    <div class="card"><p class="empty">Todavía no hay arquitecturas para este proyecto.</p></div>
@else
    <div class="card">
        <table>
            <thead>
                <tr><th>Nombre</th><th>Región</th><th>Elementos</th><th>Origen</th><th></th></tr>
            </thead>
            <tbody>
                @foreach ($architectures as $architecture)
                    <tr>
                        <td><a href="{{ route('architectures.show', $architecture) }}">{{ $architecture->name }}</a></td>
                        <td>{{ $architecture->region->name }}</td>
                        <td>{{ $architecture->items->count() }}</td>
                        <td><span class="badge">{{ $architecture->source }}</span></td>
                        <td><div class="actions" style="margin: 0;"><a class="btn btn-secondary btn-sm" href="{{ route('architectures.show', $architecture) }}">Detalle</a></div></td>
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

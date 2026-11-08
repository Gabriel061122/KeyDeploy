@extends('layouts.app')

@section('title', 'Confirmar eliminación — KeyDeploy')

@section('content')
<h1>¿Mover el proyecto a la papelera?</h1>
<div class="card">
    <dl class="detail">
        <dt>Proyecto</dt><dd>{{ $project->name }}</dd>
        <dt>Efecto del borrado</dt>
        <dd>
            <ul style="margin: 0; padding-left: 1.1rem;">
                <li>El proyecto desaparece de tus listados (borrado lógico).</li>
                <li>Los <strong>requerimientos no se eliminan</strong>.</li>
                <li>Las <strong>arquitecturas se conservan</strong> intactas.</li>
                <li>Podrás <strong>restaurarlo</strong> desde la papelera.</li>
            </ul>
        </dd>
    </dl>
    <div class="actions">
        <form method="POST" action="{{ route('projects.destroy', $project) }}">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-danger">Sí, mover a la papelera</button>
        </form>
        <a href="{{ route('projects.show', $project) }}" class="btn btn-secondary">No, volver</a>
    </div>
</div>
@endsection

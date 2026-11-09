@extends('layouts.app')

@section('title', 'Editar proyecto — KeyDeploy')

@section('content')
<h1>Editar proyecto</h1>
<div class="card">
    <form method="POST" action="{{ route('projects.update', $project) }}">
        @csrf
        @method('PUT')
        <label for="name">Nombre del proyecto</label>
        <input id="name" type="text" name="name" value="{{ old('name', $project->name) }}" maxlength="150" required>
        @error('name') <p class="field-error">{{ $message }}</p> @enderror

        <label for="description">Descripción funcional</label>
        <textarea id="description" name="description" rows="5" required>{{ old('description', $project->description) }}</textarea>
        @error('description') <p class="field-error">{{ $message }}</p> @enderror

        <label for="region_id">Región</label>
        <select id="region_id" name="region_id" required>
            @foreach ($regions as $region)
                <option value="{{ $region->id }}" @selected(old('region_id', $project->region_id) == $region->id)>{{ $region->name }} ({{ $region->code }})</option>
            @endforeach
        </select>
        @error('region_id') <p class="field-error">{{ $message }}</p> @enderror

        <label for="max_budget">Presupuesto máximo (€)</label>
        <input id="max_budget" type="number" name="max_budget" step="0.01" min="0" value="{{ old('max_budget', $project->max_budget) }}" required>
        @error('max_budget') <p class="field-error">{{ $message }}</p> @enderror

        <label for="expected_users">Número esperado de usuarios</label>
        <input id="expected_users" type="number" name="expected_users" step="1" min="1" value="{{ old('expected_users', $project->expected_users) }}" required>
        @error('expected_users') <p class="field-error">{{ $message }}</p> @enderror

        <div class="actions">
            <button type="submit" class="btn">Guardar cambios</button>
            <a href="{{ route('projects.show', $project) }}" class="btn btn-secondary">Cancelar</a>
        </div>
    </form>
</div>
@endsection

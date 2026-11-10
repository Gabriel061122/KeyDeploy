@extends('layouts.app')

@section('title', 'Editar requerimiento — '.$project->name)

@section('content')
<h1>Editar requerimiento de «{{ $project->name }}»</h1>

@include('requirements._form', [
    'project' => $project,
    'types' => $types,
    'operators' => $operators,
    'operatorLabels' => $operatorLabels,
    'selectedType' => $selectedType,
    'selectedOperator' => $selectedOperator,
    'value' => $value,
    'mandatory' => $mandatory,
    'fieldKey' => $fieldKey,
    'routeTarget' => route('projects.requirements.edit', [$project, $requirement]),
    'routeAction' => route('projects.requirements.update', [$project, $requirement]),
    'routeRefresh' => route('projects.requirements.refresh.edit', [$project, $requirement]),
    'isEdit' => true,
    'submitLabel' => 'Guardar cambios',
])
@endsection

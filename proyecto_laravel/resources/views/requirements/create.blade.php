@extends('layouts.app')

@section('title', 'Nuevo requerimiento — '.$project->name)

@section('content')
<h1>Definir requerimiento en «{{ $project->name }}»</h1>

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
    'routeTarget' => route('projects.requirements.create', $project),
    'routeAction' => route('projects.requirements.store', $project),
    'isEdit' => false,
    'submitLabel' => 'Guardar requerimiento',
])
@endsection

<?php $__env->startSection('title', 'Editar requerimiento — '.$project->name); ?>

<?php $__env->startSection('content'); ?>
<h1>Editar requerimiento de «<?php echo e($project->name); ?>»</h1>

<?php echo $__env->make('requirements._form', [
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
    'isEdit' => true,
    'submitLabel' => 'Guardar cambios',
], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/aythamir/KeyDeploy/proyecto_laravel/resources/views/requirements/edit.blade.php ENDPATH**/ ?>
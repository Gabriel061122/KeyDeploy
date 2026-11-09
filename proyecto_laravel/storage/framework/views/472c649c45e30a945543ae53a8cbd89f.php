<?php $__env->startSection('title', 'Requerimiento #'.$requirement->id.' — '.$project->name); ?>

<?php $__env->startSection('content'); ?>
<h1>Requerimiento #<?php echo e($requirement->id); ?></h1>
<div class="card">
    <dl class="detail">
        <dt>Proyecto</dt><dd><a href="<?php echo e(route('projects.show', $project)); ?>"><?php echo e($project->name); ?></a></dd>
        <dt>Campo</dt><dd><code class="inline"><?php echo e($requirement->field_key); ?></code></dd>
        <dt>Tipo de dato</dt><dd><?php echo e(\App\Services\RequirementRules::TYPE_LABELS[$requirement->data_type] ?? $requirement->data_type); ?> <span class="muted">(<?php echo e($requirement->data_type); ?>)</span></dd>
        <dt>Operador</dt><dd><?php echo e($operatorLabels[$requirement->operator] ?? $requirement->operator); ?> <span class="muted">(<?php echo e($requirement->operator); ?>)</span></dd>
        <dt>Valor</dt><dd><code class="inline"><?php echo e($requirement->value); ?></code></dd>
        <dt>Obligatoriedad</dt><dd><?php echo e($requirement->mandatory ? 'Obligatorio' : 'Opcional'); ?></dd>
        <dt>Origen</dt><dd><span class="badge"><?php echo e($requirement->source); ?></span> <span class="muted">asignado por el servidor</span></dd>
        <dt>Creado</dt><dd class="muted"><?php echo e($requirement->created_at?->format('d/m/Y H:i')); ?></dd>
        <dt>Actualizado</dt><dd class="muted"><?php echo e($requirement->updated_at?->format('d/m/Y H:i')); ?></dd>
    </dl>
    <div class="actions">
        <a href="<?php echo e(route('projects.requirements.edit', [$project, $requirement])); ?>" class="btn btn-secondary">Editar</a>
        <a href="<?php echo e(route('projects.requirements.delete', [$project, $requirement])); ?>" class="btn btn-danger">Eliminar</a>
        <a href="<?php echo e(route('projects.requirements.index', $project)); ?>" class="btn">← Requerimientos</a>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/aythamir/KeyDeploy/proyecto_laravel/resources/views/requirements/show.blade.php ENDPATH**/ ?>
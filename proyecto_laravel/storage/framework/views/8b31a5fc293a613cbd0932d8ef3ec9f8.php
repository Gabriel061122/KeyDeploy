<?php $__env->startSection('title', 'Eliminar requerimiento — '.$project->name); ?>

<?php $__env->startSection('content'); ?>
<h1>¿Eliminar este requerimiento?</h1>
<div class="card">
    <dl class="detail">
        <dt>Proyecto</dt><dd><?php echo e($project->name); ?></dd>
        <dt>Campo</dt><dd><code class="inline"><?php echo e($requirement->field_key); ?></code></dd>
        <dt>Condición</dt><dd><?php echo e($requirement->operator); ?> <code class="inline"><?php echo e($requirement->value); ?></code></dd>
        <dt>Observación</dt><dd class="muted">Esta acción es definitiva; el resto del proyecto no se ve afectado.</dd>
    </dl>
    <div class="actions">
        <form method="POST" action="<?php echo e(route('projects.requirements.destroy', [$project, $requirement])); ?>">
            <?php echo csrf_field(); ?>
            <?php echo method_field('DELETE'); ?>
            <button type="submit" class="btn btn-danger">Sí, eliminar requerimiento</button>
        </form>
        <a href="<?php echo e(route('projects.requirements.index', $project)); ?>" class="btn btn-secondary">No, volver</a>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/aythamir/KeyDeploy/proyecto_laravel/resources/views/requirements/delete.blade.php ENDPATH**/ ?>
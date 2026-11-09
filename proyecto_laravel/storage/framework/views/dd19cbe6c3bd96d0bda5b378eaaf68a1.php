<?php $__env->startSection('title', 'Requerimientos — '.$project->name); ?>

<?php $__env->startSection('content'); ?>
<div class="toolbar">
    <h1 style="margin: 0;">Requerimientos de «<?php echo e($project->name); ?>»</h1>
    <a class="btn" href="<?php echo e(route('projects.requirements.create', $project)); ?>">Nuevo requerimiento</a>
</div>

<?php if($requirements->isEmpty()): ?>
    <div class="card"><p class="empty">Este proyecto aún no tiene requerimientos estructurados.</p></div>
<?php else: ?>
    <div class="card">
        <table>
            <thead>
                <tr>
                    <th>#</th><th>Campo</th><th>Tipo</th><th>Operador</th><th>Valor</th><th>Oblig.</th><th>Origen</th><th></th>
                </tr>
            </thead>
            <tbody>
                <?php $__currentLoopData = $requirements; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $requirement): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <tr>
                        <td><?php echo e($requirement->id); ?></td>
                        <td><code class="inline"><?php echo e($requirement->field_key); ?></code></td>
                        <td><?php echo e($requirement->data_type); ?></td>
                        <td><?php echo e($requirement->operator); ?></td>
                        <td><code class="inline"><?php echo e($requirement->value); ?></code></td>
                        <td><?php echo e($requirement->mandatory ? 'Obligatorio' : 'Opcional'); ?></td>
                        <td><span class="badge"><?php echo e($requirement->source); ?></span></td>
                        <td>
                            <div class="actions" style="margin: 0;">
                                <a class="btn btn-secondary btn-sm" href="<?php echo e(route('projects.requirements.show', [$project, $requirement])); ?>">Ver</a>
                                <a class="btn btn-secondary btn-sm" href="<?php echo e(route('projects.requirements.edit', [$project, $requirement])); ?>">Editar</a>
                                <a class="btn btn-danger btn-sm" href="<?php echo e(route('projects.requirements.delete', [$project, $requirement])); ?>">Eliminar</a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<div class="actions">
    <a class="btn btn-secondary" href="<?php echo e(route('projects.show', $project)); ?>">← Volver al proyecto</a>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/aythamir/KeyDeploy/proyecto_laravel/resources/views/requirements/index.blade.php ENDPATH**/ ?>
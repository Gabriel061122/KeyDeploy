<?php $__env->startSection('title', $project->name.' — KeyDeploy'); ?>

<?php $__env->startSection('content'); ?>
<div class="toolbar">
    <h1 style="margin: 0;"><?php echo e($project->name); ?></h1>
    <div class="actions" style="margin: 0;">
        <a class="btn btn-secondary" href="<?php echo e(route('projects.edit', $project)); ?>">Editar proyecto</a>
        <a class="btn btn-danger" href="<?php echo e(route('projects.delete', $project)); ?>">Eliminar</a>
    </div>
</div>

<div class="card">
    <h2>Datos del proyecto</h2>
    <dl class="detail">
        <dt>Descripción funcional</dt><dd><?php echo e($project->description); ?></dd>
        <dt>Región</dt><dd><?php echo e($project->region->name); ?> <span class="muted">(<?php echo e($project->region->code); ?>)</span></dd>
        <dt>Presupuesto máximo</dt><dd><?php echo e(number_format((float) $project->max_budget, 2)); ?> €</dd>
        <dt>Usuarios esperados</dt><dd><?php echo e(number_format($project->expected_users)); ?></dd>
    </dl>
</div>

<div class="card">
    <div class="toolbar">
        <h2 style="margin: 0;">Requerimientos (<?php echo e($requirements->count()); ?>)</h2>
        <div class="actions" style="margin: 0;">
            <a class="btn" href="<?php echo e(route('projects.requirements.create', $project)); ?>">Nuevo requerimiento</a>
            <a class="btn btn-secondary" href="<?php echo e(route('projects.requirements.index', $project)); ?>">Lista completa</a>
        </div>
    </div>
    <?php if($requirements->isEmpty()): ?>
        <p class="empty">Sin requerimientos estructurados todavía. Añade el primero con el formulario dinámico.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr><th>Campo</th><th>Condición</th><th>Obligatoriedad</th><th>Origen</th></tr>
            </thead>
            <tbody>
                <?php $__currentLoopData = $requirements; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $requirement): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <tr>
                        <td><code class="inline"><?php echo e($requirement->field_key); ?></code></td>
                        <td><?php echo e($requirement->operator); ?> («<?php echo e($requirement->value); ?>»)</td>
                        <td><?php echo e($requirement->mandatory ? 'Obligatorio' : 'Opcional'); ?></td>
                        <td><span class="badge"><?php echo e($requirement->source); ?></span></td>
                    </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<div class="card">
    <div class="toolbar">
        <h2 style="margin: 0;">Arquitecturas (<?php echo e($architectures->count()); ?>)</h2>
        <div class="actions" style="margin: 0;">
            <a class="btn" href="<?php echo e(route('projects.architectures.create', $project)); ?>">Crear arquitectura manual</a>
            <a class="btn btn-secondary" href="<?php echo e(route('projects.architectures.index', $project)); ?>">Lista completa</a>
        </div>
    </div>
    <?php if($architectures->isEmpty()): ?>
        <p class="empty">Aún no hay arquitecturas para este proyecto.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr><th>Nombre</th><th>Región</th><th>Elementos</th><th>Origen</th></tr>
            </thead>
            <tbody>
                <?php $__currentLoopData = $architectures; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $architecture): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <tr>
                        <td><a href="<?php echo e(route('architectures.show', $architecture)); ?>"><?php echo e($architecture->name); ?></a></td>
                        <td><?php echo e($architecture->region->name); ?></td>
                        <td><?php echo e($architecture->items->count()); ?></td>
                        <td><span class="badge"><?php echo e($architecture->source); ?></span></td>
                    </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<div class="actions">
    <a class="btn btn-secondary" href="<?php echo e(route('projects.index')); ?>">← Todos los proyectos</a>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/aythamir/KeyDeploy/proyecto_laravel/resources/views/projects/show.blade.php ENDPATH**/ ?>
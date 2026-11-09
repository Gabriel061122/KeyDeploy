<?php $__env->startSection('title', 'Mis proyectos — KeyDeploy'); ?>

<?php $__env->startSection('content'); ?>
<div class="toolbar">
    <h1 style="margin: 0;">Mis proyectos</h1>
    <a class="btn" href="<?php echo e(route('projects.create')); ?>">Crear proyecto</a>
</div>

<?php if($projects->isEmpty()): ?>
    <div class="card">
        <p class="empty">Todavía no tienes proyectos. Crea el primero para describir tu aplicación.</p>
    </div>
<?php else: ?>
    <div class="card">
        <table>
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Región</th>
                    <th>Requerimientos</th>
                    <th>Arquitecturas</th>
                    <th>Presupuesto</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php $__currentLoopData = $projects; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $project): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <tr>
                        <td><a href="<?php echo e(route('projects.show', $project)); ?>"><?php echo e($project->name); ?></a></td>
                        <td><?php echo e($project->region?->name ?? '—'); ?></td>
                        <td><?php echo e($project->requirements_count); ?></td>
                        <td><?php echo e($project->architectures_count); ?></td>
                        <td><?php echo e(number_format((float) $project->max_budget, 2)); ?> €</td>
                        <td>
                            <a class="btn btn-secondary btn-sm" href="<?php echo e(route('projects.edit', $project)); ?>">Editar</a>
                            <a class="btn btn-danger btn-sm" href="<?php echo e(route('projects.delete', $project)); ?>">Eliminar</a>
                        </td>
                    </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>


<?php echo e($projects->links()); ?>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/aythamir/KeyDeploy/proyecto_laravel/resources/views/projects/index.blade.php ENDPATH**/ ?>
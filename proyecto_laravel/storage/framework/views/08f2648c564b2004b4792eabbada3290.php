<?php $__env->startSection('title', 'Papelera — KeyDeploy'); ?>

<?php $__env->startSection('content'); ?>
<div class="toolbar">
    <h1 style="margin: 0;">Papelera de proyectos</h1>
    <a class="btn btn-secondary" href="<?php echo e(route('projects.index')); ?>">Volver a mis proyectos</a>
</div>

<?php if($projects->isEmpty()): ?>
    <div class="card"><p class="empty">La papelera está vacía.</p></div>
<?php else: ?>
    <div class="card">
        <table>
            <thead>
                <tr><th>Proyecto</th><th>Región</th><th>Eliminado</th><th></th></tr>
            </thead>
            <tbody>
                <?php $__currentLoopData = $projects; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $project): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <tr>
                        <td><?php echo e($project->name); ?></td>
                        <td><?php echo e($project->region?->name ?? '—'); ?></td>
                        <td class="muted"><?php echo e($project->deleted_at?->format('d/m/Y H:i')); ?></td>
                        <td>
                            <form method="POST" action="<?php echo e(route('projects.restore', $project)); ?>">
                                <?php echo csrf_field(); ?>
                                <button type="submit" class="btn btn-secondary btn-sm">Restaurar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>


<?php echo e($projects->links()); ?>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/aythamir/KeyDeploy/proyecto_laravel/resources/views/projects/trash.blade.php ENDPATH**/ ?>
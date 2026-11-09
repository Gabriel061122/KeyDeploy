<?php $__env->startSection('title', 'Confirmar eliminación — KeyDeploy'); ?>

<?php $__env->startSection('content'); ?>
<h1>¿Mover el proyecto a la papelera?</h1>
<div class="card">
    <dl class="detail">
        <dt>Proyecto</dt><dd><?php echo e($project->name); ?></dd>
        <dt>Efecto del borrado</dt>
        <dd>
            <ul style="margin: 0; padding-left: 1.1rem;">
                <li>El proyecto desaparece de tus listados (borrado lógico).</li>
                <li>Los <strong>requerimientos no se eliminan</strong>.</li>
                <li>Las <strong>arquitecturas se conservan</strong> intactas.</li>
                <li>Podrás <strong>restaurarlo</strong> desde la papelera.</li>
            </ul>
        </dd>
    </dl>
    <div class="actions">
        <form method="POST" action="<?php echo e(route('projects.destroy', $project)); ?>">
            <?php echo csrf_field(); ?>
            <?php echo method_field('DELETE'); ?>
            <button type="submit" class="btn btn-danger">Sí, mover a la papelera</button>
        </form>
        <a href="<?php echo e(route('projects.show', $project)); ?>" class="btn btn-secondary">No, volver</a>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/aythamir/KeyDeploy/proyecto_laravel/resources/views/projects/delete.blade.php ENDPATH**/ ?>
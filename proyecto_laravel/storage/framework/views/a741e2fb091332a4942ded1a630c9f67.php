<?php $__env->startSection('title', 'KeyDeploy'); ?>

<?php $__env->startSection('content'); ?>
<div class="card">
    <h1>KeyDeploy</h1>
    <p>
        Describe tu aplicación, estructura los requerimientos y crea tus arquitecturas manualmente;
        la contratación queda simulada en esta versión.
    </p>
    <?php if(auth()->guard()->check()): ?>
        <div class="actions">
            <a class="btn" href="<?php echo e(route('projects.index')); ?>">Ir a mis proyectos</a>
        </div>
    <?php endif; ?>
    <?php if(auth()->guard()->guest()): ?>
        <div class="actions">
            <a class="btn" href="<?php echo e(route('login')); ?>">Iniciar sesión</a>
            <a class="btn btn-secondary" href="<?php echo e(route('register')); ?>">Registrarse</a>
        </div>
    <?php endif; ?>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/aythamir/KeyDeploy/proyecto_laravel/resources/views/welcome.blade.php ENDPATH**/ ?>
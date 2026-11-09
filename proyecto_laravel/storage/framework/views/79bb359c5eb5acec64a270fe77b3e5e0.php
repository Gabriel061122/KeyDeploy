<?php $__env->startSection('title', 'Acceso — KeyDeploy'); ?>

<?php $__env->startSection('content'); ?>
<div class="card" style="max-width: 28rem;">
    <h1>Iniciar sesión</h1>
    <p class="muted">Accede con tu cuenta de KeyDeploy.</p>
    <form method="POST" action="<?php echo e(route('login.store')); ?>">
        <?php echo csrf_field(); ?>
        <label for="email">Correo electrónico</label>
        <input id="email" type="email" name="email" value="<?php echo e(old('email')); ?>" required autofocus>
        <?php $__errorArgs = ['email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="field-error"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>

        <label for="password">Contraseña</label>
        <input id="password" type="password" name="password" required>
        <?php $__errorArgs = ['password'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="field-error"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>

        <div class="actions">
            <button type="submit" class="btn">Iniciar sesión</button>
            <a href="<?php echo e(route('register')); ?>" class="btn btn-secondary">Crear una cuenta</a>
        </div>
    </form>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/aythamir/KeyDeploy/proyecto_laravel/resources/views/auth/login.blade.php ENDPATH**/ ?>
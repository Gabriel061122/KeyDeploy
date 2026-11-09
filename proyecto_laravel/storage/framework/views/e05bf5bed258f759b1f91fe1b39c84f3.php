<?php $__env->startSection('title', 'Registro — KeyDeploy'); ?>

<?php $__env->startSection('content'); ?>
<div class="card" style="max-width: 28rem;">
    <h1>Registrarse</h1>
    <p class="muted">Al registrarte obtienes una cuenta de <strong>Cliente</strong> para describir tus proyectos (RF14: el registro público solo asigna el rol CLIENTE).</p>
    <form method="POST" action="<?php echo e(route('register.store')); ?>">
        <?php echo csrf_field(); ?>
        <label for="name">Nombre</label>
        <input id="name" type="text" name="name" value="<?php echo e(old('name')); ?>" required autofocus>
        <?php $__errorArgs = ['name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="field-error"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>

        <label for="email">Correo electrónico</label>
        <input id="email" type="email" name="email" value="<?php echo e(old('email')); ?>" required>
        <?php $__errorArgs = ['email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="field-error"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>

        <label for="password">Contraseña (8+ caracteres, letras y números)</label>
        <input id="password" type="password" name="password" required>
        <?php $__errorArgs = ['password'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="field-error"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>

        <label for="password_confirmation">Repite la contraseña</label>
        <input id="password_confirmation" type="password" name="password_confirmation" required>

        <div class="actions">
            <button type="submit" class="btn">Crear cuenta</button>
            <a href="<?php echo e(route('login')); ?>" class="btn btn-secondary">Ya tengo cuenta</a>
        </div>
    </form>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/aythamir/KeyDeploy/proyecto_laravel/resources/views/auth/register.blade.php ENDPATH**/ ?>
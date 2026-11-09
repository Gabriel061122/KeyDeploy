<?php $__env->startSection('title', 'Nuevo proyecto — KeyDeploy'); ?>

<?php $__env->startSection('content'); ?>
<h1>Crear proyecto</h1>
<div class="card">
    <form method="POST" action="<?php echo e(route('projects.store')); ?>">
        <?php echo csrf_field(); ?>
        <label for="name">Nombre del proyecto</label>
        <input id="name" type="text" name="name" value="<?php echo e(old('name')); ?>" maxlength="150" required>
        <?php $__errorArgs = ['name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="field-error"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>

        <label for="description">Descripción funcional</label>
        <textarea id="description" name="description" rows="5" required><?php echo e(old('description')); ?></textarea>
        <p class="muted">Describe qué hace tu aplicación y qué necesita su infraestructura.</p>
        <?php $__errorArgs = ['description'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="field-error"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>

        <label for="region_id">Región</label>
        <select id="region_id" name="region_id" required>
            <option value="">— Elige una región —</option>
            <?php $__currentLoopData = $regions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $region): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($region->id); ?>" <?php if(old('region_id') == $region->id): echo 'selected'; endif; ?>><?php echo e($region->name); ?> (<?php echo e($region->code); ?>)</option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>
        <?php $__errorArgs = ['region_id'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="field-error"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>

        <label for="max_budget">Presupuesto máximo (€)</label>
        <input id="max_budget" type="number" name="max_budget" step="0.01" min="0" value="<?php echo e(old('max_budget')); ?>" required>
        <?php $__errorArgs = ['max_budget'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="field-error"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>

        <label for="expected_users">Número esperado de usuarios</label>
        <input id="expected_users" type="number" name="expected_users" step="1" min="1" value="<?php echo e(old('expected_users')); ?>" required>
        <?php $__errorArgs = ['expected_users'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="field-error"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>

        <div class="actions">
            <button type="submit" class="btn">Crear proyecto</button>
            <a href="<?php echo e(route('projects.index')); ?>" class="btn btn-secondary">Cancelar</a>
        </div>
    </form>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/aythamir/KeyDeploy/proyecto_laravel/resources/views/projects/create.blade.php ENDPATH**/ ?>
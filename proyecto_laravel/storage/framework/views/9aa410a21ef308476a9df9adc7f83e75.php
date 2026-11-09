
<h2>Formulario de actualización del tipo</h2>
<form method="GET" action="<?php echo e($routeTarget); ?>" class="card" style="border-left: 4px solid var(--kdd);">
    <fieldset>
        <legend><strong>Paso 1 — Elige el tipo de dato</strong></legend>
        <p class="muted">Pulsa «Actualizar» para recargar la página con los operadores y el campo de valor adecuados. Lo que ya hayas tecleado se conserva.</p>
        <input type="hidden" name="field_key" value="<?php echo e($fieldKey); ?>">
        <input type="hidden" name="operator" value="<?php echo e($selectedOperator); ?>">
        <input type="hidden" name="value" value="<?php echo e($value); ?>">
        <?php if($mandatory): ?>
            <input type="hidden" name="mandatory" value="1">
        <?php endif; ?>
        <label for="data_type" style="margin-top: 0;">Tipo de dato</label>
        <div style="display: flex; gap: 0.5rem; align-items: stretch;">
            <select id="data_type" name="data_type">
                <?php $__currentLoopData = $types; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $type): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($type); ?>" <?php if($type === $selectedType): echo 'selected'; endif; ?>><?php echo e(\App\Services\RequirementRules::TYPE_LABELS[$type] ?? $type); ?></option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
            <button type="submit" class="btn">Actualizar</button>
        </div>
        <?php $__errorArgs = ['data_type'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="field-error"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
    </fieldset>
</form>

<form method="POST" action="<?php echo e($routeAction); ?>" class="card">
    <?php echo csrf_field(); ?>
    <?php if($isEdit): ?>
        <?php echo method_field('PUT'); ?>
    <?php endif; ?>

    <h2>Paso 2 — Datos del requerimiento</h2>

    <label for="field_key">Campo</label>
    <input id="field_key" type="text" name="field_key" value="<?php echo e($fieldKey); ?>" maxlength="100" placeholder="por ejemplo: almacenamiento_gb" required>
    <?php $__errorArgs = ['field_key'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="field-error"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>

    <label for="operator">Operador (<?php echo e(\App\Services\RequirementRules::TYPE_LABELS[$selectedType] ?? $selectedType); ?>)</label>
    <select id="operator" name="operator">
        <option value="">— Elige un operador —</option>
        <?php $__currentLoopData = $operators; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $operator): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <option value="<?php echo e($operator); ?>" <?php if($operator === $selectedOperator): echo 'selected'; endif; ?>>
                <?php echo e($operatorLabels[$operator] ?? $operator); ?> (<?php echo e($operator); ?>)
            </option>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </select>
    <?php $__errorArgs = ['operator'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="field-error"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>

    <label for="value">Valor</label>
    <?php switch($selectedType):
        case ('boolean'): ?>
            <select id="value" name="value">
                <option value="1"<?php if(strtolower((string) $value) === '1' || strtolower((string) $value) === 'true' || strtolower((string) $value) === 'verdadero' || strtolower((string) $value) === 'si' || strtolower((string) $value) === 'sí'): echo 'selected'; endif; ?>>Verdadero</option>
                <option value="0"<?php if(strtolower((string) $value) === '0' || strtolower((string) $value) === 'false' || strtolower((string) $value) === 'falso' || strtolower((string) $value) === 'no'): echo 'selected'; endif; ?>>Falso</option>
            </select>
        <?php break; ?>
        <?php case ('integer'): ?>
            <input id="value" type="number" name="value" step="1" value="<?php echo e($value); ?>">
        <?php break; ?>
        <?php case ('decimal'): ?>
            <input id="value" type="number" name="value" step="any" value="<?php echo e($value); ?>">
        <?php break; ?>
        <?php default: ?>
            <input id="value" type="text" name="value" value="<?php echo e($value); ?>" maxlength="500">
    <?php endswitch; ?>
    <?php $__errorArgs = ['value'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="field-error"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>

    <label style="font-weight: 600; margin-top: 1rem;">
        <input type="checkbox" name="mandatory" value="1" <?php if((bool) $mandatory): echo 'checked'; endif; ?> style="margin-right: 0.4rem;">
        Condición obligatoria
    </label>

    <div class="actions">
        <button type="submit" class="btn"><?php echo e($submitLabel); ?></button>
        <a href="<?php echo e(route('projects.show', $project)); ?>" class="btn btn-secondary">Cancelar</a>
    </div>
</form>
<?php /**PATH /home/aythamir/KeyDeploy/proyecto_laravel/resources/views/requirements/_form.blade.php ENDPATH**/ ?>
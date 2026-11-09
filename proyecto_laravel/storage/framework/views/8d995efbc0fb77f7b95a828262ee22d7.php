<?php $__env->startSection('title', 'Nueva arquitectura — '.$project->name); ?>

<?php $__env->startSection('content'); ?>
<h1>Crear arquitectura manual en «<?php echo e($project->name); ?>»</h1>


<form method="GET" action="<?php echo e(route('projects.architectures.create', $project)); ?>" class="card">
    <input type="hidden" name="name" value="<?php echo e($name); ?>">
    <?php for($i = 0; $i < $slots; $i++): ?>
        <input type="hidden" name="items[<?php echo e($i); ?>][product_id]" value="<?php echo e($rowState[$i]['product_id']); ?>">
        <input type="hidden" name="items[<?php echo e($i); ?>][quantity]" value="<?php echo e($rowState[$i]['quantity']); ?>">
        <input type="hidden" name="items[<?php echo e($i); ?>][config]" value="<?php echo e($rowState[$i]['config']); ?>">
    <?php endfor; ?>
    <label for="region_id">Región de la arquitectura</label>
    <div style="display: flex; gap: 0.5rem; align-items: stretch;">
        <select id="region_id" name="region_id">
            <option value="">— Elige una región —</option>
            <?php $__currentLoopData = $regions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $region): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($region->id); ?>" <?php if((int) $region->id === $selectedRegionId): echo 'selected'; endif; ?>><?php echo e($region->name); ?> (<?php echo e($region->code); ?>)<?php echo e($region->is_eu ? ' [UE]' : ''); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>
        <button type="submit" class="btn">Actualizar</button>
    </div>
    <?php $__errorArgs = ['region_id'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="field-error"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
    <p class="muted">Al actualizar se muestra el catálogo disponible en la región elegida: productos retirados o no disponibles no podrán usarse (RN05).</p>
</form>


<form method="POST" action="<?php echo e(route('projects.architectures.review', $project)); ?>" class="card">
    <?php echo csrf_field(); ?>
    <h2>Datos de la arquitectura</h2>

    <label for="name">Nombre de la arquitectura</label>
    <input id="name" type="text" name="name" value="<?php echo e($name); ?>" maxlength="150" required>
    <?php $__errorArgs = ['name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="field-error"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>

    <label>Región (paso 1)</label>
    <input type="hidden" name="region_id" value="<?php echo e($selectedRegionId); ?>">
    <input type="text" value="<?php echo e($regions->firstWhere('id', $selectedRegionId)?->name ?? '— no seleccionada —'); ?>" disabled>
    <?php if(! $selectedRegionId): ?>
        <p class="field-error">Elige la región en el paso 1 y pulsa «Actualizar».</p>
    <?php endif; ?>

    <?php if($products->isEmpty()): ?>
        <?php if($selectedRegionId): ?>
            <p class="field-error">No hay productos disponibles en la región seleccionada. Elige otra región y pulsa «Actualizar».</p>
        <?php endif; ?>
    <?php endif; ?>

    <h2 style="margin-top: 1.5rem;">Elementos (<?php echo e($slots); ?> huecos; se exigen al menos 3 y 2 tipos distintos)</h2>
    <?php for($i = 0; $i < $slots; $i++): ?>
        <fieldset class="slot">
            <legend>Elemento <?php echo e($i + 1); ?></legend>
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 0.75rem;">
                <div>
                    <label for="product_id_<?php echo e($i); ?>">Producto del catálogo</label>
                    <select id="product_id_<?php echo e($i); ?>" name="items[<?php echo e($i); ?>][product_id]">
                        <option value="">— sin producto —</option>
                        <?php $__currentLoopData = $products; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $product): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($product->id); ?>" <?php if((string) $rowState[$i]['product_id'] === (string) $product->id): echo 'selected'; endif; ?>>
                                <?php echo e($product->name); ?> [<?php echo e($product->code); ?>] (<?php echo e($product->type instanceof \App\Enums\ProductType ? $product->type->label() : $product->type); ?>) — <?php echo e($product->provider?->name); ?> — <?php echo e(number_format($product->priceInRegion($selectedRegionId) ?? 0, 2)); ?> €/<?php echo e($product->billing_unit); ?>

                            </option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                    <?php $__errorArgs = ['items.'.$i.'.product_id'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="field-error"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                </div>
                <div>
                    <label for="quantity_<?php echo e($i); ?>">Cantidad</label>
                    <input id="quantity_<?php echo e($i); ?>" type="number" name="items[<?php echo e($i); ?>][quantity]" min="1" step="1" value="<?php echo e($rowState[$i]['quantity']); ?>">
                    <?php $__errorArgs = ['items.'.$i.'.quantity'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="field-error"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                </div>
            </div>
            <label for="config_<?php echo e($i); ?>">Configuración JSON individual</label>
            <textarea id="config_<?php echo e($i); ?>" name="items[<?php echo e($i); ?>][config]" placeholder='{"vcpu": 2, "ram_gb": 4}'><?php echo e($rowState[$i]['config']); ?></textarea>
            <?php $__errorArgs = ['items.'.$i.'.config'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="field-error"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
        </fieldset>
    <?php endfor; ?>

    <div class="actions">
        <button type="submit" class="btn">Revisar y previsualizar</button>
        <?php if(session()->has(\App\Http\Controllers\ArchitectureController::DRAFT_KEY)): ?>
            <a class="btn btn-secondary" href="<?php echo e(route('projects.architectures.check', $project)); ?>">Ir a la revisión pendiente</a>
        <?php endif; ?>
        <a class="btn btn-secondary" href="<?php echo e(route('projects.show', $project)); ?>">Cancelar</a>
    </div>
</form>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/aythamir/KeyDeploy/proyecto_laravel/resources/views/architectures/create.blade.php ENDPATH**/ ?>
<?php $__env->startSection('title', 'Revisar arquitectura — '.$project->name); ?>

<?php $__env->startSection('content'); ?>
<h1>Revisar arquitectura antes de guardar</h1>

<div class="card">
    <h3>Datos generales</h3>
    <dl class="detail">
        <dt>Proyecto</dt><dd><a href="<?php echo e(route('projects.show', $project)); ?>"><?php echo e($project->name); ?></a></dd>
        <dt>Nombre</dt><dd><?php echo e($draft['name']); ?></dd>
        <dt>Región</dt><dd><?php echo e($region->name); ?> <span class="muted">(<?php echo e($region->code); ?>)<?php echo e($region->is_eu ? ' [UE]' : ''); ?></span></dd>
        <dt>Origen</dt><dd><span class="badge">manual</span></dd>
    </dl>
</div>

<div class="card">
    <h3>Elementos (<?php echo e(count($draft['items'])); ?>)</h3>
    <?php if(count($draft['items']) < 3): ?>
        <p class="field-error">La arquitectura necesita al menos 3 elementos; no podrá guardarse.</p>
    <?php endif; ?>
    <table>
        <thead>
            <tr><th>#</th><th>Producto</th><th>Proveedor</th><th>Tipo</th><th>Cantidad</th><th>Tarifa regional</th><th>Configuración</th></tr>
        </thead>
        <tbody>
            <?php $__currentLoopData = $draft['items']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php
                    $product = $products->get($item['product_id']);
                    $price = $product?->priceInRegion($region);
                ?>
                <tr>
                    <td><?php echo e($index + 1); ?></td>
                    <td><strong><?php echo e($product?->name); ?></strong> <code class="inline"><?php echo e($product?->code); ?></code></td>
                    <td><?php echo e($product?->provider?->name ?? '—'); ?></td>
                    <td><span class="badge"><?php echo e($product?->type instanceof \App\Enums\ProductType ? $product->type->label() : $product?->type); ?></span></td>
                    <td><?php echo e($item['quantity']); ?></td>
                    <td><?php echo e($price !== null ? number_format($price, 2).' € / '.$product?->billing_unit : '—'); ?></td>
                    <td><pre class="config"><?php echo e(json_encode($item['config'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?></pre></td>
                </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </tbody>
    </table>
    <p class="muted">Comprobaciones aplicadas: elementos y tipos mínimos, productos existentes y activos y 
    disponibilidad en la región (RN05). El guardado final revalida todo de nuevo dentro de una transacción atómica.</p>
</div>

<div class="actions">
    <form method="POST" action="<?php echo e(route('projects.architectures.store', $project)); ?>">
        <?php echo csrf_field(); ?>
        <button type="submit" class="btn">Confirmar y guardar arquitectura</button>
    </form>
    <a class="btn btn-secondary" href="<?php echo e(route('projects.architectures.create', $project)); ?>">Volver al formulario</a>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/aythamir/KeyDeploy/proyecto_laravel/resources/views/architectures/preview.blade.php ENDPATH**/ ?>
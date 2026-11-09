<?php $__env->startSection('title', $architecture->name.' — Arquitectura manual'); ?>

<?php $__env->startSection('content'); ?>
<div class="toolbar">
    <h1 style="margin: 0;"><?php echo e($architecture->name); ?></h1>
    <a class="btn btn-secondary" href="<?php echo e(route('projects.show', $architecture->project)); ?>">Volver al proyecto</a>
</div>

<div class="card">
    <h2>Datos de la arquitectura</h2>
    <dl class="detail">
        <dt>Proyecto</dt><dd><a href="<?php echo e(route('projects.show', $architecture->project)); ?>"><?php echo e($architecture->project->name); ?></a></dd>
        <dt>Región asignada</dt><dd><?php echo e($architecture->region->name); ?> <span class="muted">(<?php echo e($architecture->region->code); ?>)<?php echo e($architecture->region->is_eu ? ' [UE]' : ''); ?></span></dd>
        <dt>Origen de la arquitectura</dt><dd><span class="badge"><?php echo e($architecture->source); ?></span></dd>
        <dt>Fecha de diseño</dt><dd><?php echo e($architecture->created_at->format('d/m/Y H:i')); ?></dd>
    </dl>
</div>

<div class="card">
    <h2>Elementos y configuración (<?php echo e($architecture->items->count()); ?>)</h2>
    <table>
        <thead>
            <tr><th>#</th><th>Producto</th><th>Proveedor</th><th>Tipo</th><th>Cantidad</th><th>Tarifa regional</th><th>Configuración</th></tr>
        </thead>
        <tbody>
            <?php $__currentLoopData = $architecture->items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php
                    $price = $item->product->priceInRegion($architecture->region);
                ?>
                <tr>
                    <td><?php echo e($index + 1); ?></td>
                    <td><strong><?php echo e($item->product->name); ?></strong> <code class="inline"><?php echo e($item->product->code); ?></code></td>
                    <td><?php echo e($item->product->provider?->name ?? '—'); ?></td>
                    <td><span class="badge"><?php echo e($item->product->type instanceof \App\Enums\ProductType ? $item->product->type->label() : $item->product->type); ?></span></td>
                    <td><?php echo e($item->quantity); ?></td>
                    <td><?php echo e($price !== null ? number_format($price, 2).' € / '.$item->product->billing_unit : '—'); ?></td>
                    <td><pre class="config"><?php echo e(json_encode($item->config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?></pre></td>
                </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </tbody>
    </table>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/aythamir/KeyDeploy/proyecto_laravel/resources/views/architectures/show.blade.php ENDPATH**/ ?>
@extends('layouts.app')

@section('title', 'Revisar arquitectura — '.$project->name)

@section('content')
<h1>Revisar arquitectura antes de guardar</h1>

<div class="card">
    <h3>Datos generales</h3>
    <dl class="detail">
        <dt>Proyecto</dt><dd><a href="{{ route('projects.show', $project) }}">{{ $project->name }}</a></dd>
        <dt>Nombre</dt><dd>{{ $draft['name'] }}</dd>
        <dt>Región</dt><dd>{{ $region->name }} <span class="muted">({{ $region->code }}){{ $region->is_eu ? ' [UE]' : '' }}</span></dd>
        <dt>Origen</dt><dd><span class="badge">manual</span></dd>
    </dl>
</div>

<div class="card">
    <h3>Elementos ({{ count($draft['items']) }})</h3>
    @if (count($draft['items']) < 3)
        <p class="field-error">La arquitectura necesita al menos 3 elementos; no podrá guardarse.</p>
    @endif
    <table>
        <thead>
            <tr><th>#</th><th>Producto</th><th>Proveedor</th><th>Tipo</th><th>Modalidad</th><th>Cantidad</th><th>Tarifa regional</th><th>Consumo mensual</th><th>Subtotal mensual</th><th>Cargo único</th><th>Configuración</th></tr>
        </thead>
        <tbody>
            @foreach ($draft['items'] as $index => $item)
                @php
                    $product = $products->get($item['product_id']);
                    $price = $product?->priceInRegion($region);
                    $line = $pricing['lines'][$index] ?? null;
                @endphp
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td><strong>{{ $product?->name }}</strong> <code class="inline">{{ $product?->code }}</code></td>
                    <td>{{ $product?->provider?->name ?? '—' }}</td>
                    <td><span class="badge">{{ $product?->type instanceof \App\Enums\ProductType ? $product->type->label() : $product?->type }}</span></td>
                    <td>{{ $line['pricing_model_label'] ?? '—' }}</td>
                    <td>{{ $item['quantity'] }}</td>
                    <td>{{ $price !== null ? number_format($price, 2).' € / '.$product?->billing_unit : '—' }}</td>
                    <td>
                        @if ($line && $line['usage_field'] === \App\Services\ArchitecturePricingService::HOURS_FIELD)
                            {{ number_format($line['usage'], 2) }} h/mes
                        @elseif ($line && $line['usage_field'] === \App\Services\ArchitecturePricingService::GB_FIELD)
                            {{ number_format($line['usage'], 2) }} GB/mes
                        @else
                            —
                        @endif
                    </td>
                    <td>{{ $line ? number_format($line['monthly_total_cents'] / 100, 2).' €/mes' : '—' }}</td>
                    <td>{{ $line ? number_format($line['one_time_total_cents'] / 100, 2).' €' : '—' }}</td>
                    <td><pre class="config">{{ json_encode($item['config'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre></td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <p class="muted">Comprobaciones aplicadas: elementos y tipos mínimos, región del proyecto, productos existentes y activos,
    disponibilidad en la región (RN05), requisitos obligatorios del proyecto, consumos mensuales válidos y tarifas leídas del catálogo. El guardado final revalida todo de nuevo dentro de una transacción atómica.</p>
</div>

@if ($pricing)
    <div class="card">
        <h3>Resumen calculado</h3>
        <dl class="detail">
            <dt>Subtotal mensual</dt>
            <dd>{{ number_format($pricing['subtotal_monthly_cents'] / 100, 2) }} €/mes</dd>
            <dt>Pago único</dt>
            <dd>{{ number_format($pricing['subtotal_one_time_cents'] / 100, 2) }} €</dd>
            <dt>Coste inicial (un mes + pago único)</dt>
            <dd><strong>{{ number_format($pricing['initial_cost_cents'] / 100, 2) }} €</strong></dd>
        </dl>
        <p class="muted">Las tarifas se han vuelto a leer del catálogo en el servidor; los importes del formulario nunca se usan como precios oficiales.</p>
    </div>
@elseif ($pricingError)
    <div class="card">
        <p class="field-error">{{ $pricingError }}</p>
        <p class="muted">Corrige la arquitectura antes de guardarla: no se persistirá ningún cálculo incompleto.</p>
    </div>
@endif

@if (! empty($warnings))
    <div class="card" style="background:#fffbeb">
        <h3>Avisos no bloqueantes</h3>
        <ul>
            @foreach ($warnings as $warning)
                <li>{{ $warning }}</li>
            @endforeach
        </ul>
        <p class="muted">Puedes continuar y guardar la arquitectura; el aviso no impide el guardado.</p>
    </div>
@endif

<div class="actions">
    @if ($pricing)
        <form method="POST" action="{{ route('projects.architectures.store', $project) }}">
            @csrf
            <button type="submit" class="btn">Confirmar y guardar arquitectura</button>
        </form>
    @endif
    <a class="btn btn-secondary" href="{{ route('projects.architectures.create', $project) }}">Volver al formulario</a>
</div>
@endsection

@extends('layouts.app')

@section('title', $architecture->name.' — Arquitectura manual')

@section('content')
<div class="toolbar">
    <h1 style="margin: 0;">{{ $architecture->name }}</h1>
    <div class="actions" style="margin: 0;">
        <a class="btn btn-secondary" href="{{ route('projects.show', $architecture->project) }}">Volver al proyecto</a>
        @can('emitQuote', $architecture)
            <form method="POST" action="{{ route('architectures.quotes.emit', $architecture) }}" style="margin: 0;">
                @csrf
                <button type="submit" class="btn">Emitir presupuesto desde esta arquitectura</button>
            </form>
        @endcan
    </div>
</div>

<div class="card">
    <h2>Datos de la arquitectura</h2>
    <dl class="detail">
        <dt>Proyecto</dt><dd><a href="{{ route('projects.show', $architecture->project) }}">{{ $architecture->project->name }}</a></dd>
        <dt>Región asignada</dt><dd>{{ $architecture->region->name }} <span class="muted">({{ $architecture->region->code }}){{ $architecture->region->is_eu ? ' [UE]' : '' }}</span></dd>
        <dt>Origen de la arquitectura</dt><dd><span class="badge">{{ $architecture->source }}</span></dd>
        <dt>Fecha de diseño</dt><dd>{{ $architecture->created_at->format('d/m/Y H:i') }}</dd>
    </dl>
</div>

<div class="card">
    <h2>Elementos y configuración ({{ $architecture->items->count() }})</h2>
    <table>
        <thead>
            <tr><th>#</th><th>Producto</th><th>Proveedor</th><th>Tipo</th><th>Modalidad</th><th>Cantidad</th><th>Tarifa regional</th><th>Consumo mensual</th><th>Subtotal mensual</th><th>Cargo único</th><th>Configuración</th></tr>
        </thead>
        <tbody>
            @foreach ($architecture->items as $index => $item)
                @php
                    $price = $item->product->priceInRegion($architecture->region);
                    $line = $pricing['lines'][$index] ?? null;
                @endphp
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td><strong>{{ $item->product->name }}</strong> <code class="inline">{{ $item->product->code }}</code></td>
                    <td>{{ $item->product->provider?->name ?? '—' }}</td>
                    <td><span class="badge">{{ $item->product->type instanceof \App\Enums\ProductType ? $item->product->type->label() : $item->product->type }}</span></td>
                    <td>{{ $line['pricing_model_label'] ?? '—' }}</td>
                    <td>{{ $item->quantity }}</td>
                    <td>{{ $price !== null ? number_format($price, 2).' € / '.$item->product->billing_unit : '—' }}</td>
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
                    <td><pre class="config">{{ json_encode($item->config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre></td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

@if ($pricing)
    <div class="card" style="background:#f8fafc">
        <h2>Resumen de costes</h2>
        <dl class="detail">
            <dt>Subtotal mensual</dt>
            <dd>{{ number_format($pricing['subtotal_monthly_cents'] / 100, 2) }} €/mes</dd>
            <dt>Pago único</dt>
            <dd>{{ number_format($pricing['subtotal_one_time_cents'] / 100, 2) }} €</dd>
            <dt style="font-size:1.05rem"><strong>Coste inicial</strong></dt>
            <dd style="font-size:1.05rem"><strong>{{ number_format($pricing['initial_cost_cents'] / 100, 2) }} €</strong></dd>
        </dl>
        <p class="muted" style="margin-top:1rem">El coste inicial suma un mes de tarifa recurrente y los cargos únicos. Los importes se recalculan con las tarifas vigentes del catálogo en la región de la arquitectura.</p>
    </div>
@elseif ($pricingError)
    <div class="card">
        <p class="field-error">{{ $pricingError }}</p>
        <p class="muted">No se puede mostrar un desglose completo porque falta una tarifa válida o un consumo obligatorio.</p>
    </div>
@endif

@if (! empty($warnings))
    <div class="card" style="background:#fffbeb">
        <h2>Avisos no bloqueantes</h2>
        <ul>
            @foreach ($warnings as $warning)
                <li>{{ $warning }}</li>
            @endforeach
        </ul>
        <p class="muted">El aviso no altera la arquitectura guardada ni impide continuar con el presupuesto.</p>
    </div>
@endif
@endsection

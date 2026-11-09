@extends('layouts.app')

@section('title', 'Presupuesto #' . $quote->id)

@section('content')
@if (session('warning'))
    <div class="card" style="background:#fffbeb;border-color:#fcd34d">
        <h2>Aviso de presupuesto</h2>
        <p>{{ session('warning') }}</p>
        <p class="muted">El aviso no bloquea la emisión ni altera los importes del presupuesto.</p>
    </div>
@endif

<div class="toolbar">
    <h1>Presupuesto #{{ $quote->id }}</h1>
    <div class="flex gap-2">
        <a href="{{ route('projects.show', $quote->project) }}" class="btn btn-secondary">Volver al proyecto</a>
        <button onclick="window.print()" class="btn btn-secondary">Imprimir</button>
    </div>
</div>

<div class="card">
    <h2>Información general</h2>
    <dl class="detail">
        <dt>Proyecto</dt>
        <dd>{{ $quote->project->name }}</dd>
        <dt>Estado</dt>
        <dd><span class="badge">{{ ucfirst($quote->status) }}</span></dd>
        <dt>Fecha de emisión</dt>
        <dd>{{ $quote->issued_at->format('d/m/Y H:i') }}</dd>
        <dt>Emitted por</dt>
        <dd>{{ $quote->user->name }}</dd>
    </dl>
</div>

<div class="card">
    <h2>Líneas del presupuesto</h2>
    <table>
        <thead>
            <tr>
                <th>Producto</th>
                <th>Tipo</th>
                <th>Modalidad</th>
                <th>Cantidad</th>
                <th>Precio unitario</th>
                <th>Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($quote->lines as $line)
                <tr>
                    <td><strong>{{ $line->product_name }}</strong></td>
                    <td><span class="badge">{{ $line->product_type }}</span></td>
                    <td>
                        @php
                            $pricingModelLabels = [
                                'mensual' => ['Mensual', 'background:#fef3c7;border-color:#fcd34d;color:#92400e'],
                                'fijo_mensual' => ['Fijo mensual', 'background:#fef3c7;border-color:#fcd34d;color:#92400e'],
                                'por_hora' => ['Por hora', 'background:#fce7f3;border-color:#f9a8d4;color:#9d174d'],
                                'por_gb' => ['Por GB', 'background:#ede9fe;border-color:#c4b5fd;color:#5b21b6'],
                                'unico' => ['Pago único', 'background:#dbeafe;border-color:#93c5fd;color:#1e40af'],
                                'por_uso' => ['Por uso', 'background:#fce7f3;border-color:#f9a8d4;color:#9d174d'],
                            ];
                            [$pricingModelLabel, $pricingModelStyle] = $pricingModelLabels[$line->pricing_model]
                                ?? [$line->pricing_model, 'background:#f1f5f9;border-color:#cbd5e1;color:#334155'];
                        @endphp
                        <span class="badge" style="{{ $pricingModelStyle }}">{{ $pricingModelLabel }}</span>
                    </td>
                    <td>{{ $line->quantity }}</td>
                    <td>{{ number_format($line->unit_price_cents / 100, 2) }} €</td>
                    <td>{{ number_format($line->line_total_cents / 100, 2) }} €</td>
                </tr>
                @if ($line->config)
                    <tr>
                        <td colspan="7" style="padding-left:2rem">
                            <pre class="config" style="margin:0.25rem 0">{{ json_encode($line->config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                        </td>
                    </tr>
                @endif
            @endforeach
        </tbody>
    </table>
</div>

<div class="card" style="background:#f8fafc">
    <h2>Resumen de totales</h2>
    <dl class="detail">
        <dt>Subtotal mensual</dt>
        <dd>{{ number_format($quote->subtotal_monthly_cents / 100, 2) }} €/mes</dd>
        <dt>Subtotal pago único</dt>
        <dd>{{ number_format($quote->subtotal_one_time_cents / 100, 2) }} €</dd>
        <dt style="font-size:1.1rem"><strong>Total</strong></dt>
        <dd style="font-size:1.1rem"><strong>{{ number_format($quote->total_cents / 100, 2) }} €</strong></dd>
    </dl>
    <p class="muted" style="margin-top:1rem">
        Este presupuesto es un snapshot histórico. Los precios y nombres congelados no cambiarán aunque el catálogo se modifique.
    </p>
</div>
@endsection

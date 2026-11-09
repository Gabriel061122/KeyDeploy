@extends('layouts.app')

@section('title', 'Carrito de compra')

@section('content')
<div class="toolbar">
    <h1>Carrito de compra</h1>
    @if ($items->isNotEmpty())
        <a href="{{ route('projects.index') }}" class="btn btn-secondary">Seguir añadiendo productos</a>
    @endif
</div>

@if ($items->isEmpty())
    <div class="card">
        <p class="empty">Tu carrito está vacío. Explora los proyectos para añadir productos.</p>
        <div class="actions">
            <a href="{{ route('projects.index') }}" class="btn">Ver proyectos</a>
        </div>
    </div>
@else
    <div class="card">
        <table>
            <thead>
                <tr>
                    <th>Producto</th>
                    <th>Tipo</th>
                    <th>Precio unitario</th>
                    <th style="width:120px">Cantidad</th>
                    <th style="width:140px">Subtotal</th>
                    <th style="width:80px"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($items as $item)
                    <tr>
                        <td><strong>{{ $item->product->name }}</strong></td>
                        <td><span class="badge">{{ $item->product->type }}</span></td>
                        <td>{{ number_format($item->product->unit_price, 2) }} €</td>
                        <td>
                            <form method="POST" action="{{ route('cart.update', $item) }}" class="flex items-center gap-2">
                                @csrf
                                @method('PATCH')
                                <input type="number" name="quantity" value="{{ $item->quantity }}" min="1" max="100"
                                       class="w-20 text-center" style="width:80px">
                                <button type="submit" class="btn btn-sm btn-secondary">Actualizar</button>
                            </form>
                        </td>
                        <td>{{ number_format($item->product->unit_price * $item->quantity, 2) }} €</td>
                        <td>
                            <form method="POST" action="{{ route('cart.destroy', $item) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-danger" title="Eliminar">✕</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="actions" style="justify-content:space-between">
            <div>
                <strong>Total: {{ number_format($totalCents / 100, 2) }} €</strong>
                <span class="muted"> ({{ $items->count() }} producto{{ $items->count() > 1 ? 's' : '' }})</span>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('projects.index') }}" class="btn btn-secondary">Añadir más</a>
                @if (auth()->user()->projects()->exists())
                    <a href="#emitir" class="btn">Emitir presupuesto</a>
                @else
                    <span class="muted" style="align-self:center">Crea un proyecto para emitir presupuesto</span>
                @endif
            </div>
        </div>
    </div>
@endif
@endsection

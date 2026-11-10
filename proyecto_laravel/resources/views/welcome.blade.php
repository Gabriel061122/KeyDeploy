@extends('layouts.app')

@section('title', 'Inicio · KeyDeploy')

@section('content')
    <h1 class="text-2xl font-bold mb-2">KeyDeploy</h1>
    <p class="mb-4">Hay {{ $productCount }} productos activos en el catálogo.</p>
    <p class="mb-5"><a class="btn" href="{{ route('products.index') }}">Explorar catálogo</a></p>
    <div class="grid md:grid-cols-3 gap-4">
        @forelse ($featured as $product)
            <x-product-card :product="$product" />
        @empty
            <p class="text-slate-600">Todavía no hay productos publicados.</p>
        @endforelse
    </div>
@endsection

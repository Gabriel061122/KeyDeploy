<?php

@extends('layouts.app')
@section('title', 'Inicio · KeyDeploy')
@section('content')
    <h1 class="text-2xl font-bold mb-2">KeyDeploy (versión de desarrollo UT2)</h1>
    <p class="mb-4">Hay {{ $productCount }} productos activos en el catálogo.</p>
    <div class="grid md:grid-cols-3 gap-4">
        @foreach ($featured as $product)
            <x-product-card :product="$product" />
        @endforeach
    </div>
@endsection
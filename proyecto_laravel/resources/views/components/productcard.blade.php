<?php

@props(['product'])
<div class="bg-white rounded shadow p-4">
    <h3 class="font-semibold"><a href="{{ route('products.show', $product) }}">{{ $product->name }}</a></h3>
    <p class="text-sm text-gray-500">{{ $product->type->label() }} · {{ $product->provider->name }}</p>
    <p class="text-sm mt-2">
        Regiones:
        @forelse ($product->availabilities->where('available', true) as $a)
            <span class="inline-block bg-blue-100 rounded px-2 text-xs">{{ $a->region->code }}</span>
        @empty
            <em>sin disponibilidad</em>
        @endforelse
    </p>
</div>
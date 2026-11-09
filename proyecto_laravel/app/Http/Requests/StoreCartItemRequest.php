<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCartItemRequest extends FormRequest
{
    /**
     * Solo el usuario autenticado puede añadir al carrito.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'product_id' => 'required|integer|exists:products,id',
            'quantity' => 'integer|min:1|max:100',
            'config' => 'array|nullable',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'product_id.required' => 'Debes seleccionar un producto.',
            'product_id.exists' => 'El producto seleccionado no existe.',
            'quantity.min' => 'La cantidad debe ser al menos 1.',
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCartItemRequest extends FormRequest
{
    /**
     * Solo el propietario del elemento puede modificarlo.
     */
    public function authorize(): bool
    {
        $item = $this->route('item');
        return $item && $item->user_id === auth()->id();
    }

    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'quantity' => 'required|integer|min:1|max:100',
            'config' => 'array|nullable',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'quantity.required' => 'La cantidad es obligatoria.',
            'quantity.min' => 'La cantidad debe ser al menos 1.',
        ];
    }
}

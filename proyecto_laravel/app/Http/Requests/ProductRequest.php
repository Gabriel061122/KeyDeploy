<?php

namespace App\Http\Requests;

use App\Enums\ProductType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $type = ProductType::tryFrom((string) $this->input('type'));

        return array_merge([
            'provider_id' => ['required', 'integer', 'exists:providers,id'],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'type' => ['required', Rule::enum(ProductType::class)],
            'billing_unit' => ['required', 'string', 'max:20'],
            'active' => ['sometimes', 'boolean'],
            'prices' => ['nullable', 'array'],
            'prices.*' => ['nullable', 'integer', 'min:0'],
        ], $type?->configRules() ?? []);
    }
}

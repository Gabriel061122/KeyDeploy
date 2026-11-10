<?php

namespace App\Http\Requests;

use App\Enums\ProductType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CatalogFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['nullable', Rule::enum(ProductType::class)],
            'provider_id' => ['nullable', 'integer', 'exists:providers,id'],
            'region_id' => ['nullable', 'integer', 'exists:regions,id'],
            'q' => ['nullable', 'string', 'max:60'],
        ];
    }
}

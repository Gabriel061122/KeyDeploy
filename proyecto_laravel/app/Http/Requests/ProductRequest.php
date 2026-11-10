<?php

namespace App\Http\Requests;

use App\Enums\ProductType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $config = $this->input('config');

        if (is_string($config)) {
            $decodedConfig = json_decode($config, true);

            if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decodedConfig)) {
                throw ValidationException::withMessages([
                    'config' => 'La configuración debe ser un objeto JSON válido.',
                ]);
            }

            $this->merge(['config' => $decodedConfig]);
        }
    }

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
            'config' => ['required', 'array'],
            'active' => ['sometimes', 'boolean'],
            'prices' => ['nullable', 'array'],
            'prices.*' => ['nullable', 'integer', 'min:0'],
        ], $type?->configRules() ?? []);
    }
}

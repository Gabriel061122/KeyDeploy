<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreProjectFileRequest extends FormRequest
{
    /**
     * Solo el usuario autenticado puede subir ficheros.
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
            'file' => 'required|file|mimes:pdf,png,jpg,jpeg,docx,md|max:10240',
            'architecture_id' => 'nullable|integer|exists:architectures,id',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Debes seleccionar un fichero.',
            'file.mimes' => 'El fichero debe ser PDF, PNG, JPG, DOCX o MD.',
            'file.max' => 'El fichero no puede superar los 10 MB.',
        ];
    }
}

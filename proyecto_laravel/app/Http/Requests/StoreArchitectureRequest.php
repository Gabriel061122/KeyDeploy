<?php

namespace App\Http\Requests;

use App\Models\Architecture;
use App\Models\Project;
use App\Models\Region;
use App\Services\ArchitectureValidator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Creación manual de una arquitectura. Cubre las reglas de campo
 * (región válida, identificadores de producto existentes, cantidades
 * enteras positivas) y las reglas de dominio de varias tablas delega en
 * `ArchitectureValidator`: mínimo de elementos y de tipos, estado activo
 * y disponbilidad real del producto en la región (RN05).
 */
class StoreArchitectureRequest extends FormRequest
{
    public const ITEM_SLOTS = 6;

    public function __construct(public ArchitectureValidator $architectureValidator)
    {
        parent::__construct();
    }

    public function authorize(): bool
    {
        $project = $this->route('project');

        return $project instanceof Project
            && Gate::forUser($this->user())->allows('create', [Architecture::class, $project]);
    }

    /**
     * @return array<string, array<int, string>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'region_id' => ['required', 'integer', Rule::exists('regions', 'id')],
            'items' => ['required', 'array', 'min:'.ArchitectureValidator::MIN_ITEMS, 'max:'.self::ITEM_SLOTS],
            'items.*.product_id' => ['required', 'integer', Rule::exists('products', 'id')],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99999'],
            'items.*.config' => ['required', 'string', 'max:5000'],
        ];
    }

    /**
     * Reglas de dominio dependientes de varias tablas, comprobadas
     * también en el servidor (la IA u otro formulario no las sustituyen,
     * RNF01/RN06).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->any()) {
                return;
            }

            $region = Region::find($this->integer('region_id'));
            $items = $this->input('items', []);

            if ($region === null || ! is_array($items)) {
                return;
            }

            $result = $this->architectureValidator->validate($region, $items);

            foreach ($result['errors'] as $error) {
                $validator->errors()->add('items', $error);
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre de la arquitectura es obligatorio.',
            'region_id.required' => 'La región es obligatoria.',
            'region_id.exists' => 'La región seleccionada no existe o no es válida.',
            'items.required' => 'Debes añadir los elementos de la arquitectura.',
            'items.min' => 'La arquitectura debe contener al menos :min elementos.',
            'items.max' => 'El formulario admite como máximo :max elementos.',
            'items.*.product_id.required' => 'Cada elemento necesita un producto del catálogo.',
            'items.*.product_id.exists' => 'Un elemento apunta a un producto que no existe en el catálogo.',
            'items.*.quantity.required' => 'Cada elemento necesita una cantidad.',
            'items.*.quantity.min' => 'Cada cantidad debe ser un número entero mayor o igual que 1.',
            'items.*.config.required' => 'Cada elemento necesita una configuración JSON válida (usa {} si no requiere valores).',
            'items.*.config.max' => 'La configuración de un elemento es demasiado larga.',
        ];
    }
}

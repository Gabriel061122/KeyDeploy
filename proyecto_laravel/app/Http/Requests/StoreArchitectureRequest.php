<?php

namespace App\Http\Requests;

use App\Models\Architecture;
use App\Models\Product;
use App\Models\Project;
use App\Models\Region;
use App\Services\ArchitectureBusinessRules;
use App\Services\ArchitectureValidator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Creación manual de una arquitectura. Cubre las reglas de campo
 * (región válida, identificadores de producto existentes, cantidades
 * enteras positivas y consumo mensual opcional) y las reglas de dominio
 * de varias tablas delega en `ArchitectureBusinessRules`: mínimo de
 * elementos y de tipos, región del proyecto, estado activo y
 * disponibilidad real del producto en la región (RN05) y requisitos
 * obligatorios del proyecto.
 *
 * Los huecos totalmente vacíos del formulario se descartan antes de
 * validar; la configuración JSON del cliente nunca se acepta y se ignora
 * cualquier `items[*][config]` recibido.
 */
class StoreArchitectureRequest extends FormRequest
{
    public const ITEM_SLOTS = 6;

    public function __construct(
        public ArchitectureBusinessRules $businessRules,
    ) {
        parent::__construct();
    }

    public function authorize(): bool
    {
        $project = $this->route('project');

        return $project instanceof Project
            && Gate::forUser($this->user())->allows('create', [Architecture::class, $project]);
    }

    /**
     * Descarta los huecos vacíos sin reindexar filas, para que los
     * errores sigan citando el número de hueco visible. Solo se conservan
     * producto, cantidad y consumo: cualquier configuración enviada por el
     * navegador se descarta.
     */
    protected function prepareForValidation(): void
    {
        $items = $this->input('items');

        if (! is_array($items)) {
            return;
        }

        $filtered = [];

        foreach ($items as $index => $item) {
            if (! is_array($item)) {
                continue;
            }

            $productId = $item['product_id'] ?? null;
            $quantity = $item['quantity'] ?? null;
            $hasProduct = $productId !== null && $productId !== '';
            $hasQuantity = $quantity !== null && trim((string) $quantity) !== '';

            if (! $hasProduct && ! $hasQuantity) {
                continue;
            }

            $filtered[$index] = [
                'product_id' => $productId,
                'quantity' => $quantity,
                'usage' => $item['usage'] ?? null,
            ];
        }

        $this->merge(['items' => $filtered]);
    }

    /**
     * @return array<string, array<int, string>|string>
     */
    public function rules(): array
    {
        $project = $this->route('project');
        $regionRules = ['required', 'integer', Rule::exists('regions', 'id')];

        if ($project instanceof Project) {
            $regionRules[] = Rule::in([(int) $project->region_id]);
        }

        return [
            'name' => ['required', 'string', 'max:150'],
            'region_id' => $regionRules,
            'items' => ['required', 'array', 'min:'.ArchitectureValidator::MIN_ITEMS, 'max:'.self::ITEM_SLOTS],
            'items.*.product_id' => ['nullable', 'integer', Rule::exists('products', 'id')],
            'items.*.quantity' => ['nullable', 'integer', 'min:1', 'max:99999'],
            'items.*.usage' => ['nullable', 'numeric', 'min:0', 'max:999999999.99', 'decimal:0,2'],
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
            $this->checkPartialRows($validator);

            $project = $this->route('project');
            $region = Region::find($this->integer('region_id'));

            if ($region !== null && ! Product::query()->availableIn($region)->exists()) {
                $validator->errors()->add('region_id', sprintf(
                    'La región "%s" no tiene productos activos y disponibles; elige otra región.',
                    $region->name,
                ));
            }

            $regionMismatch = $project instanceof Project
                && $region !== null
                && (int) $project->region_id !== (int) $region->id;

            if ($validator->errors()->any()) {
                $onlyRegionMismatch = $regionMismatch
                    && array_diff($validator->errors()->keys(), ['region_id']) === [];

                if (! $onlyRegionMismatch) {
                    return;
                }
            }

            $items = $this->input('items', []);

            if (! $project instanceof Project || $region === null || ! is_array($items)) {
                return;
            }

            $result = $this->businessRules->evaluate($project, $region, $items);

            foreach ($result['errors'] as $error) {
                $validator->errors()->add('items', $error);
            }
        });
    }

    /**
     * Una fila con producto pero sin cantidad (o al revés) es un envío
     * incompleto: se informa citando el hueco visible del formulario.
     */
    private function checkPartialRows(Validator $validator): void
    {
        $items = $this->input('items', []);

        if (! is_array($items)) {
            return;
        }

        foreach ($items as $index => $item) {
            if (! is_array($item)) {
                continue;
            }

            $productId = $item['product_id'] ?? null;
            $quantity = $item['quantity'] ?? null;
            $hasProduct = $productId !== null && $productId !== '';
            $hasQuantity = $quantity !== null && trim((string) $quantity) !== '';

            if ($hasProduct === $hasQuantity) {
                continue;
            }

            $validator->errors()->add('items', sprintf(
                'El hueco %d está incompleto: %s.',
                (int) $index + 1,
                $hasProduct
                    ? 'indica la cantidad del producto seleccionado'
                    : 'selecciona el producto de la cantidad indicada',
            ));
        }
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
            'region_id.in' => 'La región de la arquitectura debe coincidir con la región del proyecto.',
            'items.required' => 'Debes añadir los elementos de la arquitectura.',
            'items.array' => 'Los elementos de la arquitectura no tienen un formato válido.',
            'items.min' => 'La arquitectura debe contener al menos :min elementos.',
            'items.max' => 'El formulario admite como máximo :max elementos.',
            'items.*.product_id.integer' => 'El producto de un elemento no es válido.',
            'items.*.product_id.exists' => 'Un elemento apunta a un producto que no existe en el catálogo.',
            'items.*.quantity.integer' => 'Cada cantidad debe ser un número entero mayor o igual que 1.',
            'items.*.quantity.min' => 'Cada cantidad debe ser un número entero mayor o igual que 1.',
            'items.*.quantity.max' => 'La cantidad de un elemento supera el máximo permitido.',
            'items.*.usage.numeric' => 'El consumo mensual debe ser un número.',
            'items.*.usage.min' => 'El consumo mensual no puede ser negativo.',
            'items.*.usage.max' => 'El consumo mensual supera el máximo permitido.',
            'items.*.usage.decimal' => 'El consumo mensual admite como máximo :decimal decimales.',
        ];
    }
}

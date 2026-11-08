<?php

namespace App\Models;

use Database\Factories\RequirementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Requerimiento estructurado de un Proyecto (RF03): campo, operador,
 * valor y condición obligatoria u opcional, con la procedencia asignada
 * desde el servidor (`manual` o `ia`).
 *
 * @property int $id
 * @property int $project_id
 * @property string $field_key
 * @property string $operator
 * @property string $value
 * @property string $data_type
 * @property bool $mandatory
 * @property string $source
 */
class Requirement extends Model
{
    /** @use HasFactory<RequirementFactory> */
    use HasFactory;

    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_IA = 'ia';

    /**
     * `source` y `project_id` no son asignables en masa: el primero lo
     * impone el servidor y el segundo lo resuelve la ruta anidada.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'field_key',
        'operator',
        'value',
        'data_type',
        'mandatory',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mandatory' => 'boolean',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}

<?php

namespace App\Models;

use Database\Factories\ArchitectureFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Arquitectura asociada a un Proyecto. La procedencia `manual` la
 * impone el servidor; `ia` corresponde al flujo de propuestas RF04.
 *
 * @property int $id
 * @property int $project_id
 * @property int $region_id
 * @property string $name
 * @property string $source
 */
class Architecture extends Model
{
    /** @use HasFactory<ArchitectureFactory> */
    use HasFactory;

    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_IA = 'ia';

    /**
     * `source` no es asignable en masa en este módulo manual: la impone
     * el servidor para que el cliente no pueda falsear la procedencia.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'region_id',
        'name',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ArchitectureItem::class)->orderBy('id');
    }

    /**
     * Ficheros adjuntos a esta arquitectura.
     */
    public function files(): HasMany
    {
        return $this->hasMany(ProjectFile::class, 'architecture_id');
    }
}

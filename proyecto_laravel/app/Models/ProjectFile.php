<?php

namespace App\Models;

use Database\Factories\ProjectFileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Fichero adjunto a un proyecto o arquitectura.
 *
 * @property int $id
 * @property int $project_id
 * @property int|null $architecture_id
 * @property string $original_name
 * @property string $storage_path
 * @property string $mime_type
 * @property int $size_bytes
 */
class ProjectFile extends Model
{
    /** @use HasFactory<ProjectFileFactory> */
    use HasFactory;

    /**
     * Los metadatos se calculan al subir el fichero; `project_id` y
     * `architecture_id` se fijan desde el controlador. `user_id` no
     * es asignable en masa.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'project_id',
        'architecture_id',
        'original_name',
        'storage_path',
        'mime_type',
        'size_bytes',
    ];

    /**
     * Proyecto al que pertenece el fichero.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Arquitectura opcional a la que pertenece el fichero.
     */
    public function architecture(): BelongsTo
    {
        return $this->belongsTo(Architecture::class);
    }
}

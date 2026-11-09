<?php

namespace App\Models;

use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Proyecto del CLIENTE (RF02/CU02).
 *
 * @property int $id
 * @property int $user_id
 * @property int $region_id
 * @property string $name
 * @property string $description
 * @property float $max_budget
 * @property int $expected_users
 */
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory, SoftDeletes;

    /**
     * `user_id` nunca es asignable en masa: el propietario se fija en el
     * servidor a partir del usuario autenticado (RF17/RN09).
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'region_id',
        'name',
        'description',
        'max_budget',
        'expected_users',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'max_budget' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function requirements(): HasMany
    {
        return $this->hasMany(Requirement::class);
    }

    public function architectures(): HasMany
    {
        return $this->hasMany(Architecture::class)->orderBy('id');
    }

    /**
     * Presupuestos emitidos para este proyecto.
     */
    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }

    /**
     * Ficheros adjuntos al proyecto.
     */
    public function files(): HasMany
    {
        return $this->hasMany(ProjectFile::class);
    }

    /**
     * Ámbito de trabajo habitual: los proyectos del usuario autenticado.
     *
     * @param  Builder<Project>  $query
     * @return Builder<Project>
     */
    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }
}

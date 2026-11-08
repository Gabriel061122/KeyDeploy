<?php

namespace App\Models;

use Database\Factories\RegionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Región del catálogo (contrato de integración EP03/RF12).
 *
 * @property int $id
 * @property string $code
 * @property string $name
 */
class Region extends Model
{
    /** @use HasFactory<RegionFactory> */
    use HasFactory;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'code',
        'name',
    ];

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function architectures(): HasMany
    {
        return $this->hasMany(Architecture::class);
    }
}

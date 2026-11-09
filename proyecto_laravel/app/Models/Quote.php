<?php

namespace App\Models;

use Database\Factories\QuoteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Presupuesto emitido (snapshot histórico inmutable).
 *
 * @property int $id
 * @property int $project_id
 * @property int $user_id
 * @property string $status
 * @property int $subtotal_monthly_cents
 * @property int $subtotal_one_time_cents
 * @property int $total_cents
 * @property Carbon $issued_at
 */
class Quote extends Model
{
    /** @use HasFactory<QuoteFactory> */
    use HasFactory;

    /**
     * Los totales y el estado se calculan en el servicio; no son
     * asignables en masa para evitar manipulación del cliente.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'project_id',
        'user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
        ];
    }

    /**
     * Proyecto al que pertenece este presupuesto.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Usuario que emitió el presupuesto.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Líneas congeladas del presupuesto.
     */
    public function lines(): HasMany
    {
        return $this->hasMany(QuoteLine::class);
    }
}

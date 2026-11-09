<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Roles contemplados por RF01. El registro público solo asigna
     * CLIENTE (RF14); el resto los gestiona el ADMINISTRADOR (RF16).
     *
     * @var list<string>
     */
    public const ROLES = ['cliente', 'arquitecto', 'auditor', 'administrador'];

    public const ROLE_CLIENTE = 'cliente';

    /**
     * Etiqueta de cada rol para la interfaz.
     *
     * @var array<string, string>
     */
    public const ROLE_LABELS = [
        'cliente' => 'Cliente',
        'arquitecto' => 'Arquitecto',
        'auditor' => 'Auditor',
        'administrador' => 'Administrador',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Comprueba el rol de la cuenta. `role` no es asignable en masa; los
     * controladores lo establecen explícitamente tras validar los datos.
     */
    public function hasRole(string $role): bool
    {
        return in_array($role, self::ROLES, true) && $this->role === $role;
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /**
     * Elementos del carrito de compra del usuario.
     */
    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    /**
     * Presupuestos emitidos por el usuario.
     */
    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }
}

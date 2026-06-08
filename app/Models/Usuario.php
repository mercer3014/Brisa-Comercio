<?php

namespace App\Models;

use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Usuario extends Model implements AuthenticatableContract
{
    use Authenticatable;

    protected $table = 'usuario';
    protected $primaryKey = 'usuario_id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'nombre_usuario', 'correo', 'hash_contrasena', 'nombre_completo',
        'activo', 'debe_cambiar_pwd', 'ultimo_acceso',
    ];

    protected $hidden = ['hash_contrasena'];

    protected $casts = [
        'activo' => 'boolean',
        'debe_cambiar_pwd' => 'boolean',
        'fecha_creacion' => 'datetime',
        'ultimo_acceso' => 'datetime',
    ];

    /**
     * La contrasenia se guarda en hash_contrasena (no en "password").
     */
    public function getAuthPassword(): string
    {
        return $this->hash_contrasena;
    }

    public function getAuthPasswordName(): string
    {
        return 'hash_contrasena';
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Rol::class, 'usuario_rol', 'usuario_id', 'rol_id')
            ->withPivot('asignado_en');
    }

    public function sesiones(): HasMany
    {
        return $this->hasMany(Sesion::class, 'usuario_id', 'usuario_id');
    }

    /**
     * Codigos de permiso del usuario, derivados de sus roles
     * (usuario_rol -> rol_permiso -> permiso).
     */
    public function codigosPermisos(): array
    {
        return $this->roles()
            ->with('permisos:permiso_id,codigo')
            ->get()
            ->pluck('permisos')
            ->flatten()
            ->pluck('codigo')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Verifica si el usuario tiene un permiso por su codigo.
     */
    public function tienePermiso(string $codigo): bool
    {
        return in_array($codigo, $this->codigosPermisos(), true);
    }

    /**
     * Verifica si el usuario tiene alguno de los roles dados (por nombre).
     */
    public function tieneRol(string ...$nombres): bool
    {
        return $this->roles->pluck('nombre')->intersect($nombres)->isNotEmpty();
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CargaArchivo extends Model
{
    protected $table = 'carga_archivo';
    protected $primaryKey = 'carga_id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'organizacion_id', 'fuente_id', 'perfil_id', 'usuario_id',
        'nombre_archivo', 'tipo_flujo', 'gestion', 'mes',
        'total_filas_leidas', 'total_filas_validas', 'total_filas_error',
        'estado', 'fecha_carga',
    ];

    protected $casts = [
        'fecha_carga' => 'datetime',
    ];

    public function organizacion(): BelongsTo
    {
        return $this->belongsTo(Organizacion::class, 'organizacion_id', 'organizacion_id');
    }

    public function fuente(): BelongsTo
    {
        return $this->belongsTo(FuenteDatos::class, 'fuente_id', 'fuente_id');
    }

    public function perfil(): BelongsTo
    {
        return $this->belongsTo(PerfilMapeo::class, 'perfil_id', 'perfil_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id', 'usuario_id');
    }

    public function procesos(): HasMany
    {
        return $this->hasMany(ProcesoEtl::class, 'carga_id', 'carga_id');
    }

    public function incidencias(): HasMany
    {
        return $this->hasMany(IncidenciaCalidad::class, 'carga_id', 'carga_id');
    }

    public function operaciones(): HasMany
    {
        return $this->hasMany(OperacionComercioExterior::class, 'carga_id', 'carga_id');
    }
}

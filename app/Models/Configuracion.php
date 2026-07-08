<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Configuracion extends Model
{
    protected $table = 'configuracion';
    protected $primaryKey = 'clave';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'clave', 'valor', 'tipo_dato', 'descripcion', 'usuario_modifico',
    ];

    protected $casts = [
        'fecha_modificacion' => 'datetime',
    ];

    public function usuarioModifico(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_modifico', 'usuario_id');
    }

    /**
     * Helper para leer un parámetro de configuración con valor por defecto.
     */
    public static function obtener(string $clave, $defecto = null)
    {
        $config = static::find($clave);

        return $config ? $config->valor : $defecto;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class Producto extends Model
{
    protected $table = 'producto';
    protected $primaryKey = 'producto_id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = ['capitulo_id', 'codigo_nandina', 'descripcion', 'vigente'];

    protected $casts = [
        'codigo_nandina' => 'integer',
        'vigente' => 'boolean',
    ];

    public function capitulo(): BelongsTo
    {
        return $this->belongsTo(CapituloArancelario::class, 'capitulo_id', 'capitulo_id');
    }

    /**
     * Acceso directo a la sección a través del capítulo (jerarquía NANDINA).
     */
    public function seccion(): HasOneThrough
    {
        return $this->hasOneThrough(
            SeccionArancelaria::class,
            CapituloArancelario::class,
            'capitulo_id',  // PK en capitulo_arancelario
            'seccion_id',   // PK en seccion_arancelaria
            'capitulo_id',  // FK local en producto
            'seccion_id'    // FK en capitulo_arancelario
        );
    }

    public function operaciones(): HasMany
    {
        return $this->hasMany(OperacionComercioExterior::class, 'producto_id', 'producto_id');
    }
}

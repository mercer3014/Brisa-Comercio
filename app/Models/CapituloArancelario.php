<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CapituloArancelario extends Model
{
    protected $table = 'capitulo_arancelario';
    protected $primaryKey = 'capitulo_id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = ['seccion_id', 'codigo_capitulo', 'descripcion'];

    public function seccion(): BelongsTo
    {
        return $this->belongsTo(SeccionArancelaria::class, 'seccion_id', 'seccion_id');
    }

    public function productos(): HasMany
    {
        return $this->hasMany(Producto::class, 'capitulo_id', 'capitulo_id');
    }
}

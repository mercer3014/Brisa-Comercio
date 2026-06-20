<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaisCodigoExterno extends Model
{
    protected $table = 'pais_codigo_externo';
    protected $primaryKey = 'pais_codigo_externo_id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $guarded = [];

    public function pais(): BelongsTo
    {
        return $this->belongsTo(Pais::class, 'pais_id', 'pais_id');
    }

    public function organizacion(): BelongsTo
    {
        return $this->belongsTo(Organizacion::class, 'organizacion_id', 'organizacion_id');
    }
}

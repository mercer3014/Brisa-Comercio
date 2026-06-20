<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArchivoFuente extends Model
{
    protected $table = 'archivo_fuente';
    protected $primaryKey = 'archivo_id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $guarded = [];

    public function organizacion(): BelongsTo
    {
        return $this->belongsTo(Organizacion::class, 'organizacion_id', 'organizacion_id');
    }

    public function paisReportante(): BelongsTo
    {
        return $this->belongsTo(Pais::class, 'pais_reportante_id', 'pais_id');
    }

    public function flujo(): BelongsTo
    {
        return $this->belongsTo(FlujoComercial::class, 'flujo_id', 'flujo_id');
    }
}

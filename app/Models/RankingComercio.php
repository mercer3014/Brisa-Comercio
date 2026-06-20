<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RankingComercio extends Model
{
    protected $table = 'ranking_comercio';
    protected $primaryKey = 'ranking_id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'es_confidencial' => 'boolean',
        'valor' => 'decimal:2',
        'porcentaje_total' => 'decimal:2',
        'valor_acumulado' => 'decimal:2',
        'porcentaje_acumulado' => 'decimal:2',
        'atributos_extra' => 'array',
    ];

    public function organizacion(): BelongsTo
    {
        return $this->belongsTo(Organizacion::class, 'organizacion_id', 'organizacion_id');
    }

    public function archivo(): BelongsTo
    {
        return $this->belongsTo(ArchivoFuente::class, 'archivo_id', 'archivo_id');
    }

    public function paisReportante(): BelongsTo
    {
        return $this->belongsTo(Pais::class, 'pais_reportante_id', 'pais_id');
    }

    public function flujo(): BelongsTo
    {
        return $this->belongsTo(FlujoComercial::class, 'flujo_id', 'flujo_id');
    }

    public function tiempo(): BelongsTo
    {
        return $this->belongsTo(Tiempo::class, 'tiempo_id', 'tiempo_id');
    }

    public function productoCodigoExterno(): BelongsTo
    {
        return $this->belongsTo(ProductoCodigoExterno::class, 'producto_codigo_externo_id', 'producto_codigo_externo_id');
    }
}

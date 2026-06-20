<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SerieComercioBilateral extends Model
{
    protected $table = 'serie_comercio_bilateral';
    protected $primaryKey = 'serie_bilateral_id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'valor_usd' => 'decimal:2',
        'volumen_kg' => 'decimal:2',
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

    public function paisSocio(): BelongsTo
    {
        return $this->belongsTo(Pais::class, 'pais_socio_id', 'pais_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id', 'producto_id');
    }

    public function productoCodigoExterno(): BelongsTo
    {
        return $this->belongsTo(ProductoCodigoExterno::class, 'producto_codigo_externo_id', 'producto_codigo_externo_id');
    }

    public function tiempo(): BelongsTo
    {
        return $this->belongsTo(Tiempo::class, 'tiempo_id', 'tiempo_id');
    }

    public function flujo(): BelongsTo
    {
        return $this->belongsTo(FlujoComercial::class, 'flujo_id', 'flujo_id');
    }
}

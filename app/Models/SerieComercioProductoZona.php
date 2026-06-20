<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SerieComercioProductoZona extends Model
{
    protected $table = 'serie_comercio_producto_zona';
    protected $primaryKey = 'serie_prod_zona_id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'exportaciones_usd' => 'decimal:2',
        'importaciones_fob_usd' => 'decimal:2',
        'importaciones_cif_usd' => 'decimal:2',
        'volumen_export_kg' => 'decimal:2',
        'volumen_import_kg' => 'decimal:2',
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

    public function zona(): BelongsTo
    {
        return $this->belongsTo(ZonaGeoeconomica::class, 'zona_id', 'zona_id');
    }

    public function productoCodigoExterno(): BelongsTo
    {
        return $this->belongsTo(ProductoCodigoExterno::class, 'producto_codigo_externo_id', 'producto_codigo_externo_id');
    }

    public function tiempo(): BelongsTo
    {
        return $this->belongsTo(Tiempo::class, 'tiempo_id', 'tiempo_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperacionComercioExterior extends Model
{
    protected $table = 'operacion_comercio_exterior';
    protected $primaryKey = 'operacion_id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'organizacion_id', 'carga_id',
        'fuente_id', 'tiempo_id', 'tipo_operacion_id', 'flujo_id', 'producto_id',
        'cuci_id', 'gce_id', 'ciiu_id', 'pais_id', 'departamento_id', 'medio_id', 'via_id',
        'tnt_id', 'cuode_id', 'aduana_id', 'despachante_id',
        'peso_bruto_kg', 'peso_neto_kg', 'peso_fino_kg',
        'valor_fob_usd', 'valor_cif_frontera_usd', 'valor_cif_aduana_usd',
        'gravamenes_pagados', 'atributos_extra',
    ];

    protected $casts = [
        'peso_bruto_kg' => 'decimal:2',
        'peso_neto_kg' => 'decimal:2',
        'peso_fino_kg' => 'decimal:2',
        'valor_fob_usd' => 'decimal:2',
        'valor_cif_frontera_usd' => 'decimal:2',
        'valor_cif_aduana_usd' => 'decimal:2',
        'gravamenes_pagados' => 'decimal:2',
        'atributos_extra' => 'array',
    ];

    // -- Catálogo / carga --------------------------------------------------
    public function organizacion(): BelongsTo
    {
        return $this->belongsTo(Organizacion::class, 'organizacion_id', 'organizacion_id');
    }

    public function carga(): BelongsTo
    {
        return $this->belongsTo(CargaArchivo::class, 'carga_id', 'carga_id');
    }

    public function fuente(): BelongsTo
    {
        return $this->belongsTo(FuenteDatos::class, 'fuente_id', 'fuente_id');
    }

    // -- Dimensiones obligatorias -----------------------------------------
    public function tiempo(): BelongsTo
    {
        return $this->belongsTo(Tiempo::class, 'tiempo_id', 'tiempo_id');
    }

    public function tipoOperacion(): BelongsTo
    {
        return $this->belongsTo(TipoOperacion::class, 'tipo_operacion_id', 'tipo_operacion_id');
    }

    public function flujo(): BelongsTo
    {
        return $this->belongsTo(FlujoComercial::class, 'flujo_id', 'flujo_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id', 'producto_id');
    }

    public function cuci(): BelongsTo
    {
        return $this->belongsTo(ClasificacionCuci::class, 'cuci_id', 'cuci_id');
    }

    public function gce(): BelongsTo
    {
        return $this->belongsTo(CategoriaEconomicaGce::class, 'gce_id', 'gce_id');
    }

    public function ciiu(): BelongsTo
    {
        return $this->belongsTo(ActividadCiiu::class, 'ciiu_id', 'ciiu_id');
    }

    public function pais(): BelongsTo
    {
        return $this->belongsTo(Pais::class, 'pais_id', 'pais_id');
    }

    public function departamento(): BelongsTo
    {
        return $this->belongsTo(Departamento::class, 'departamento_id', 'departamento_id');
    }

    public function medio(): BelongsTo
    {
        return $this->belongsTo(MedioTransporte::class, 'medio_id', 'medio_id');
    }

    public function via(): BelongsTo
    {
        return $this->belongsTo(ViaComercio::class, 'via_id', 'via_id');
    }

    // -- Dimensiones opcionales -------------------------------------------
    public function tnt(): BelongsTo
    {
        return $this->belongsTo(ClasificacionTnt::class, 'tnt_id', 'tnt_id');
    }

    public function cuode(): BelongsTo
    {
        return $this->belongsTo(ClasificacionCuode::class, 'cuode_id', 'cuode_id');
    }

    public function aduana(): BelongsTo
    {
        return $this->belongsTo(Aduana::class, 'aduana_id', 'aduana_id');
    }

    public function despachante(): BelongsTo
    {
        return $this->belongsTo(Despachante::class, 'despachante_id', 'despachante_id');
    }
}

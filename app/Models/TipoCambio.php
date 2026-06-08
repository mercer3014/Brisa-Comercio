<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoCambio extends Model
{
    protected $table = 'tipo_cambio';
    protected $primaryKey = 'tipo_cambio_id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = ['fecha', 'moneda_origen', 'moneda_destino', 'tasa'];

    protected $casts = [
        'fecha' => 'date',
        'tasa' => 'decimal:6',
    ];
}

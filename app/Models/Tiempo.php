<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tiempo extends Model
{
    protected $table = 'tiempo';
    protected $primaryKey = 'tiempo_id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'gestion', 'mes', 'nombre_mes', 'trimestre', 'semestre', 'fecha_inicio_mes',
    ];

    protected $casts = [
        'fecha_inicio_mes' => 'date',
    ];

    public function operaciones(): HasMany
    {
        return $this->hasMany(OperacionComercioExterior::class, 'tiempo_id', 'tiempo_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContenidoPortal extends Model
{
    protected $table = 'contenido_portal';
    protected $primaryKey = 'contenido_id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'activo' => 'boolean',
        'updated_at' => 'datetime',
    ];
}

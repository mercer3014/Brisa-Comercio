<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizacionMedia extends Model
{
    protected $table = 'organizacion_media';
    protected $primaryKey = 'media_id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'activo' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function organizacion(): BelongsTo
    {
        return $this->belongsTo(Organizacion::class, 'organizacion_id', 'organizacion_id');
    }
}

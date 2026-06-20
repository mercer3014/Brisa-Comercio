<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FaostatSimbolo extends Model
{
    protected $table = 'faostat_simbolo';
    protected $primaryKey = 'simbolo_id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $guarded = [];

    public function series(): HasMany
    {
        return $this->hasMany(SerieIndicadorAgricola::class, 'simbolo_id', 'simbolo_id');
    }
}

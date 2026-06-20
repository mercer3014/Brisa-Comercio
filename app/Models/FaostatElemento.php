<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FaostatElemento extends Model
{
    protected $table = 'faostat_elemento';
    protected $primaryKey = 'elemento_id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $guarded = [];

    public function series(): HasMany
    {
        return $this->hasMany(SerieIndicadorAgricola::class, 'elemento_id', 'elemento_id');
    }
}

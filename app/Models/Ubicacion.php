<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ubicacion extends Model
{
    use HasFactory;

    protected $table = 'ubicaciones';

    protected $fillable = [
        'bodega_id',
        'kobo',
        'codigo',
        'descripcion',
    ];

    public function bodega()
    {
        return $this->belongsTo(Bodega::class);
    }
}

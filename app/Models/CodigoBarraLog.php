<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CodigoBarraLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'barcode',
        'barcode_anterior',
        'sku',
        'user_id',
        'bodega_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function bodega()
    {
        return $this->belongsTo(Bodega::class);
    }
}

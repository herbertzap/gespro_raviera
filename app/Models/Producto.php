<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Producto extends Model
{
    use HasFactory;
    protected $table = 'productos';
    protected $fillable = [
        'TIPR',
        'KOPR',
        'KOPRRA',
        'KOPRTE',
        'NOKOPR',
        'NOKOPRRA',
        'UD01PR',
        'UD02PR',
        'RLUD',
        'POIVPR',
        'RGPR',
        'MRPR',
        'FMPR',
        'PFPR',
        'HFPR',
        'DIVISIBLE',
        'DIVISIBLE2',
        'FECRPR',
        'estado'
    ];
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Producto extends Model
{
    use HasFactory;
    protected $fillable = [
        'TIPR',
        'KOPR',
        'NOKOPR',
        'KOPRRA',
        'NOKOPRRA',
        'KOPRTE',
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
        'FECRPR',
        'DIVISIBLE2',
        'estado'
    ];
}

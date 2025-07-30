<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotaVenta extends Model
{
    use HasFactory;

    protected $fillable = [
        'numero_nvv',
        'fecha_nvv',
        'codigo_cliente',
        'codigo_vendedor',
        'total_nvv',
        'saldo_pendiente',
        'fecha_vencimiento',
        'estado',
        'observaciones',
        'user_id'
    ];

    protected $casts = [
        'fecha_nvv' => 'date',
        'fecha_vencimiento' => 'date',
        'total_nvv' => 'decimal:2',
        'saldo_pendiente' => 'decimal:2'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

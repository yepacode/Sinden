<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClienteImport extends Model
{
    protected $table = 'clientes_imports';

    protected $fillable = [
        'usuario_id',
        'nombre_archivo',
        'total_filas',
        'creados',
        'actualizados',
        'errores',
        'estado',
        'detalle_log',
    ];

    protected $casts = [
        'detalle_log' => 'array',
    ];

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}

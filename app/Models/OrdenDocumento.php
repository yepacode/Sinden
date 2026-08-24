<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrdenDocumento extends Model
{
    protected $table = 'orden_documentos';

    const UPDATED_AT = null;

    protected $fillable = [
        'orden_id', 'nombre_original', 'ruta_archivo', 'extension',
        'mime_type', 'tamano_bytes', 'subido_por',
    ];

    protected $casts = [
        'tamano_bytes' => 'integer',
    ];

    public function orden()
    {
        return $this->belongsTo(Orden::class, 'orden_id');
    }

    public function subidoPorUsuario()
    {
        return $this->belongsTo(User::class, 'subido_por');
    }

    public function getUrlDescargaAttribute()
    {
        return route('recepcion.ordenes.documentos.descargar', [
            'orden' => $this->orden_id,
            'documento' => $this->id,
        ]);
    }

    public function getTamanoLegibleAttribute()
    {
        $bytes = (int) $this->tamano_bytes;
        if ($bytes <= 0) return '0 B';
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = (int) floor(log($bytes, 1024));
        $i = min($i, count($units) - 1);
        $val = $bytes / pow(1024, $i);
        return number_format($val, $i === 0 ? 0 : 1, '.', ',') . ' ' . $units[$i];
    }

    public function getIconoAttribute()
    {
        $ext = strtolower((string) $this->extension);
        $map = [
            'pdf'  => 'bi-file-earmark-pdf text-danger',
            'doc'  => 'bi-file-earmark-word text-primary',
            'docx' => 'bi-file-earmark-word text-primary',
            'xls'  => 'bi-file-earmark-excel text-success',
            'xlsx' => 'bi-file-earmark-excel text-success',
            'csv'  => 'bi-file-earmark-excel text-success',
            'ppt'  => 'bi-file-earmark-ppt text-warning',
            'pptx' => 'bi-file-earmark-ppt text-warning',
            'jpg'  => 'bi-file-earmark-image text-info',
            'jpeg' => 'bi-file-earmark-image text-info',
            'png'  => 'bi-file-earmark-image text-info',
            'gif'  => 'bi-file-earmark-image text-info',
            'webp' => 'bi-file-earmark-image text-info',
            'svg'  => 'bi-file-earmark-image text-info',
            'zip'  => 'bi-file-earmark-zip text-warning',
            'rar'  => 'bi-file-earmark-zip text-warning',
            '7z'   => 'bi-file-earmark-zip text-warning',
            'txt'  => 'bi-file-earmark-text text-secondary',
        ];
        return $map[$ext] ?? 'bi-file-earmark text-secondary';
    }
}

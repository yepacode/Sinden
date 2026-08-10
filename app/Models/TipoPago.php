<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TipoPago extends Model
{
    use SoftDeletes;

    protected $table = 'tipos_pago';

    protected $fillable = [
        'codigo', 'nombre', 'icono', 'color', 'orden', 'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'orden' => 'integer',
    ];

    public function scopeActivos($query)
    {
        return $query->where('activo', true)->orderBy('orden')->orderBy('id');
    }

    /**
     * Coleccion de tipos activos para selects (cacheada por request).
     * Excluye los soft-deleted automaticamente.
     * @return \Illuminate\Support\Collection
     */
    public static function opciones()
    {
        static $cache = null;
        if ($cache === null) {
            $cache = static::activos()->get(['id', 'codigo', 'nombre', 'icono', 'color']);
        }
        return $cache;
    }

    /**
     * Paleta de colores de los tipos de pago. Fuente unica de la verdad para
     * renderizar badges con valores hex explicitos, inmunes a los overrides de
     * Bootstrap del tema (p.ej. el tema remapea .bg-success a gris acero).
     * Estructura: [color => ['hex' => texto/borde, 'bg' => fondo]]
     * @return array
     */
    public static function paletaColores(): array
    {
        // 'hex'      = texto/borde en modo CLARO (oscuro, sobre fondo pastel).
        // 'hex_dark' = texto en modo OSCURO (claro, legible sobre el tinte oscuro);
        //              el 'hex' oscuro sobre fondo oscuro quedaba invisible.
        return [
            'success'   => ['hex' => '#198754', 'hex_dark' => '#75b798', 'bg' => 'rgba(25,135,84,.15)'],
            'primary'   => ['hex' => '#0d6efd', 'hex_dark' => '#6ea8fe', 'bg' => 'rgba(13,110,253,.15)'],
            'info'      => ['hex' => '#0dcaf0', 'hex_dark' => '#6edff6', 'bg' => 'rgba(13,202,240,.18)'],
            'warning'   => ['hex' => '#b8860b', 'hex_dark' => '#ffda6a', 'bg' => 'rgba(255,193,7,.22)'],
            'danger'    => ['hex' => '#dc3545', 'hex_dark' => '#ea868f', 'bg' => 'rgba(220,53,69,.15)'],
            'secondary' => ['hex' => '#6c757d', 'hex_dark' => '#a7acb1', 'bg' => 'rgba(108,117,125,.18)'],
            'purple'    => ['hex' => '#6f42c1', 'hex_dark' => '#c29ffa', 'bg' => 'rgba(111,66,193,.15)'],
            'dark'      => ['hex' => '#212529', 'hex_dark' => '#adb5bd', 'bg' => 'rgba(33,37,41,.18)'],
        ];
    }

    /**
     * Mapa completo (incluye inactivos y soft-deleted) para renderizar badges
     * de pagos historicos. Estructura:
     * [codigo => ['color', 'icono', 'nombre', 'codigo', 'etiqueta', 'hex', 'bg']]
     * @return array
     */
    public static function mapaBadges()
    {
        static $cache = null;
        if ($cache === null) {
            $paleta = static::paletaColores();
            $cache = static::withTrashed()->orderBy('orden')->get()->mapWithKeys(function ($t) use ($paleta) {
                $pp = $paleta[$t->color] ?? $paleta['secondary'];
                return [$t->codigo => [
                    'color' => $t->color,
                    'icono' => $t->icono,
                    'nombre' => $t->nombre,
                    'codigo' => $t->codigo,
                    'etiqueta' => $t->codigo . ' - ' . $t->nombre,
                    'hex' => $pp['hex'],
                    'hex_dark' => $pp['hex_dark'],
                    'bg' => $pp['bg'],
                ]];
            })->toArray();
        }
        return $cache;
    }
}

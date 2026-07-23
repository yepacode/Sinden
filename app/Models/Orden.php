<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Orden extends Model
{
    protected $table = 'ordenes';

    protected $fillable = [
        'numero_orden', 'cliente_id', 'creado_por', 'estado_trabajo', 'estado_entrega',
        'estado_pago', 'fecha_entrega', 'hora_entrega', 'ruta_firma_cliente', 'notas',
        'subtotal', 'monto_iva', 'total', 'total_pagado', 'saldo',
        'clonada_de_id', 'bloqueada_por', 'bloqueada_en',
    ];

    protected $casts = [
        'fecha_entrega' => 'date',
        'bloqueada_en' => 'datetime',
        'subtotal' => 'decimal:2',
        'monto_iva' => 'decimal:2',
        'total' => 'decimal:2',
        'total_pagado' => 'decimal:2',
        'saldo' => 'decimal:2',
    ];

    // === Relaciones ===

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function creador()
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function ordenOriginal()
    {
        return $this->belongsTo(Orden::class, 'clonada_de_id');
    }

    public function bloqueadaPorUsuario()
    {
        return $this->belongsTo(User::class, 'bloqueada_por');
    }

    public function items()
    {
        return $this->hasMany(OrdenItem::class, 'orden_id');
    }

    public function bosquejos()
    {
        return $this->hasMany(OrdenBosquejo::class, 'orden_id');
    }

    public function piezas()
    {
        return $this->hasMany(OrdenPieza::class, 'orden_id');
    }

    public function pagos()
    {
        return $this->hasMany(Pago::class, 'orden_id');
    }

    public function fotos()
    {
        return $this->hasMany(OrdenFoto::class, 'orden_id');
    }

    public function documentos()
    {
        return $this->hasMany(OrdenDocumento::class, 'orden_id')->orderByDesc('created_at');
    }

    public function comentarios()
    {
        return $this->hasMany(OrdenComentario::class, 'orden_id');
    }

    public function asignaciones()
    {
        return $this->hasMany(AsignacionPieza::class, 'orden_id');
    }

    public function actividades()
    {
        return $this->hasMany(RegistroActividad::class, 'orden_id');
    }

    public function garantias()
    {
        return $this->hasMany(DevolucionGarantia::class, 'orden_id');
    }

    public function entregas()
    {
        return $this->hasMany(Entrega::class, 'orden_id');
    }

    // === Scopes ===

    public function scopeBorradores($query)
    {
        return $query->where('estado_trabajo', 'borrador');
    }

    public function scopeGeneradas($query)
    {
        return $query->where('estado_trabajo', 'generada');
    }

    public function scopeEnEjecucion($query)
    {
        return $query->where('estado_trabajo', 'en_ejecucion');
    }

    public function scopeEjecutadas($query)
    {
        return $query->where('estado_trabajo', 'ejecutada');
    }

    public function scopeAnuladas($query)
    {
        return $query->where('estado_trabajo', 'anulada');
    }

    public function scopeConSaldoPendiente($query)
    {
        return $query->where('estado_pago', 'saldo_pendiente');
    }

    public function scopePagadas($query)
    {
        return $query->where('estado_pago', 'pagado');
    }

    public function scopeNoAnuladas($query)
    {
        return $query->where('estado_trabajo', '!=', 'anulada');
    }

    public function scopeNoBorradores($query)
    {
        return $query->where('estado_trabajo', '!=', 'borrador');
    }

    // === Accesor de hora de entrega ===

    /**
     * Hora de entrega formateada a 12h con a. m. / p. m. (ej. "3:05 p. m.").
     * Devuelve null si no hay hora registrada.
     */
    public function getHoraEntregaFmtAttribute(): ?string
    {
        if (empty($this->hora_entrega)) {
            return null;
        }
        try {
            $t = \Carbon\Carbon::createFromFormat('H:i:s', $this->hora_entrega);
        } catch (\Exception $e) {
            try {
                $t = \Carbon\Carbon::createFromFormat('H:i', $this->hora_entrega);
            } catch (\Exception $e2) {
                return $this->hora_entrega;
            }
        }
        $sufijo = $t->hour < 12 ? 'a. m.' : 'p. m.';
        return $t->format('g:i') . ' ' . $sufijo;
    }

    // === Accesores de avance ===

    public function getPorcentajeTrabajoAttribute(): int
    {
        $piezas = $this->piezas;
        if (!$piezas || $piezas->isEmpty()) {
            return 0;
        }
        // Una pieza totalmente entregada cuenta como 100% de trabajo,
        // aunque su avance registrado sea menor (entrega sin avance).
        $valores = $piezas->map(function ($p) {
            if ($p->cantidad > 0 && $p->cantidad_entregada >= $p->cantidad) {
                return 100.0;
            }
            return (float) $p->porcentaje_avance;
        });
        $avg = (float) $valores->avg();
        return (int) max(0, min(100, round($avg)));
    }

    public function getPorcentajeEntregaAttribute(): int
    {
        $piezas = $this->piezas;
        if (!$piezas || $piezas->isEmpty()) {
            return 0;
        }
        $totalCant = (int) $piezas->sum('cantidad');
        if ($totalCant <= 0) {
            return 0;
        }
        $totalEntregada = (int) $piezas->sum('cantidad_entregada');
        $pct = ($totalEntregada / $totalCant) * 100;
        return (int) max(0, min(100, round($pct)));
    }

    public function getPorcentajePagoAttribute(): int
    {
        $total = (float) $this->total;
        if ($total <= 0) {
            return 0;
        }
        $pct = ((float) $this->total_pagado / $total) * 100;
        return (int) max(0, min(100, round($pct)));
    }

    public function getPorcentajeTotalAttribute(): ?int
    {
        if ($this->estado_trabajo === 'anulada') {
            return null;
        }
        if ($this->estado_trabajo === 'borrador') {
            return 0;
        }
        $prom = ($this->porcentaje_trabajo + $this->porcentaje_entrega + $this->porcentaje_pago) / 3;
        return (int) max(0, min(100, round($prom)));
    }

    // === Helpers de validacion de pagos ===

    /**
     * Monto que aun se puede registrar como pago nuevo.
     * Cuenta pagos visibles (aprobados + pendientes); los rechazados estan soft-deleted.
     */
    public function montoDisponibleNuevoPago(): float
    {
        $comprometido = (float) $this->pagos()->sum('monto');
        return max(0, (float) $this->total - $comprometido);
    }

    /**
     * Monto disponible para aprobar un pago especifico.
     * Excluye el pago en aprobacion para no contarlo dos veces.
     */
    public function montoDisponibleAprobacion(?int $excluirPagoId = null): float
    {
        $query = $this->pagos()->where('aprobado', true);
        if ($excluirPagoId) {
            $query->where('id', '<>', $excluirPagoId);
        }
        return max(0, (float) $this->total - (float) $query->sum('monto'));
    }
}

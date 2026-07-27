<?php

namespace App\Services;

use App\Models\Orden;
use Illuminate\Support\Facades\DB;

class OrdenEstadoService
{
    /**
     * Recalcula los 3 estados independientes + totales financieros.
     */
    public function recalcularTodo(Orden $orden): Orden
    {
        $this->recalcularTotales($orden);

        // Solo recalcular estados si no es borrador
        if ($orden->estado_trabajo !== 'borrador') {
            $orden->estado_trabajo = $this->recalcularEstadoTrabajo($orden);
            $orden->estado_entrega = $this->recalcularEstadoEntrega($orden);
        }

        $orden->estado_pago = $this->recalcularEstadoPago($orden);
        $orden->save();

        return $orden;
    }

    /**
     * Recalcula estado_trabajo segun progreso de piezas.
     */
    public function recalcularEstadoTrabajo(Orden $orden): string
    {
        // Borrador y anulada no se recalculan
        if (in_array($orden->estado_trabajo, ['borrador', 'anulada'])) {
            return $orden->estado_trabajo;
        }

        $piezas = $orden->piezas;

        // Sin piezas = venta directa = ejecutada
        if ($piezas->isEmpty()) {
            return 'ejecutada';
        }

        $totalPiezas = $piezas->count();

        // Una pieza cuenta como completada si llego al 100% de avance
        // O si ya fue entregada en su totalidad (no se puede entregar algo sin terminar).
        $completadas = $piezas->filter(function ($p) {
            return $p->porcentaje_avance >= 100
                || ($p->cantidad > 0 && $p->cantidad_entregada >= $p->cantidad);
        })->count();

        $enProgreso = $piezas->filter(function ($p) {
            return $p->porcentaje_avance > 0 || $p->cantidad_entregada > 0;
        })->count();

        if ($completadas === $totalPiezas) {
            return 'ejecutada';
        }

        if ($completadas > 0) {
            return 'ejecutada_parcialmente';
        }

        if ($enProgreso > 0) {
            return 'en_ejecucion';
        }

        return 'generada';
    }

    /**
     * Recalcula estado_entrega segun flags entregada de piezas.
     */
    public function recalcularEstadoEntrega(Orden $orden): ?string
    {
        if ($orden->estado_trabajo === 'borrador') {
            return null;
        }

        $piezas = $orden->piezas;

        // Sin piezas = venta directa = entregada
        if ($piezas->isEmpty()) {
            return 'entregada';
        }

        $totalPiezas = $piezas->count();
        $totalmenteEntregadas = $piezas->filter(fn($p) => $p->cantidad_entregada >= $p->cantidad)->count();
        $conEntregaParcial = $piezas->filter(fn($p) => $p->cantidad_entregada > 0)->count();

        if ($totalmenteEntregadas === $totalPiezas) {
            return 'entregada';
        }

        if ($conEntregaParcial > 0) {
            return 'entregada_parcialmente';
        }

        return null;
    }

    /**
     * Recalcula estado_pago segun saldo.
     */
    public function recalcularEstadoPago(Orden $orden): ?string
    {
        if ($orden->estado_trabajo === 'borrador') {
            return null;
        }

        if ($orden->saldo <= 0) {
            return 'pagado';
        }

        return 'saldo_pendiente';
    }

    /**
     * Recalcula totales financieros desde items y pagos.
     */
    public function recalcularTotales(Orden $orden): Orden
    {
        // Peso colombiano sin centavos: totales en pesos enteros para que el
        // valor mostrado sea el real y el abonable (sin decimales ocultos).
        $orden->subtotal = round($orden->items()->sum('subtotal'), 0);
        $orden->monto_iva = round($orden->items()->sum('monto_iva'), 0);
        $descuentoTotal = round($orden->items()->sum('descuento_monto'), 0);
        $orden->total = $orden->subtotal + $orden->monto_iva - $descuentoTotal;
        $orden->total_pagado = round($orden->pagos()->where('aprobado', true)->sum('monto'), 0);
        $orden->saldo = $orden->total - $orden->total_pagado;

        return $orden;
    }

    /**
     * Genera el siguiente numero consecutivo de orden.
     * Formato: "#0001". Usa lock para evitar colisiones.
     */
    public function generarNumeroConsecutivo(): string
    {
        $maxNumero = DB::table('ordenes')
            ->whereNotNull('numero_orden')
            ->lockForUpdate()
            ->max(DB::raw("CAST(REPLACE(numero_orden, '#', '') AS UNSIGNED)"));

        $siguiente = ($maxNumero ?? 0) + 1;

        return '#' . str_pad($siguiente, 4, '0', STR_PAD_LEFT);
    }
}

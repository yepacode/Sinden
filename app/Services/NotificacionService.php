<?php

namespace App\Services;

use App\Models\Notificacion;
use App\Models\User;
use App\Models\DevolucionGarantia;
use App\Models\Orden;
use App\Models\OrdenPieza;
use App\Models\Pago;

class NotificacionService
{
    /**
     * Enviar notificacion a usuario(s) especificos.
     */
    public static function notificar($usuarios, string $tipo, string $titulo, string $contenido, ?string $url = null): void
    {
        if ($usuarios instanceof User) {
            $usuarios = collect([$usuarios]);
        } elseif (is_array($usuarios)) {
            $usuarios = User::whereIn('id', $usuarios)->activos()->get();
        }

        // No enviar notificaciones a usuarios desactivados
        $usuarios = collect($usuarios)->filter(fn ($u) => $u->activo);

        foreach ($usuarios as $usuario) {
            Notificacion::create([
                'usuario_id' => $usuario->id,
                'tipo' => $tipo,
                'titulo' => $titulo,
                'contenido' => $contenido,
                'url' => $url,
            ]);
        }
    }

    /**
     * Enviar notificacion a todos los usuarios con los roles dados.
     */
    public static function notificarRoles(array $roles, string $tipo, string $titulo, string $contenido, ?string $url = null): void
    {
        $usuarios = User::role($roles)->activos()->get();
        static::notificar($usuarios, $tipo, $titulo, $contenido, $url);
    }

    // ──────────────────────────────────────────
    // Garantias
    // ──────────────────────────────────────────

    public static function garantiaRegistrada(DevolucionGarantia $garantia, Orden $orden): void
    {
        $pieza = $garantia->pieza;
        $url = "/recepcion/ordenes/{$orden->id}";

        static::notificarRoles(
            ['Administrador'],
            'garantia_registrada',
            'Garantia registrada',
            "Nueva garantia para pieza '{$pieza->nombre}' (Orden #{$orden->numero_orden})",
            $url
        );

        if ($garantia->operario_asignado_id) {
            static::notificar(
                User::find($garantia->operario_asignado_id),
                'garantia_asignada',
                'Garantia asignada',
                "Se te asigno una garantia para pieza '{$pieza->nombre}' (Orden #{$orden->numero_orden})",
                '/operario/garantias'
            );
        }
    }

    public static function garantiaAsignada(DevolucionGarantia $garantia): void
    {
        $pieza = $garantia->pieza;
        $orden = $garantia->orden;

        static::notificar(
            User::find($garantia->operario_asignado_id),
            'garantia_asignada',
            'Garantia asignada',
            "Se te asigno una garantia para pieza '{$pieza->nombre}' (Orden #{$orden->numero_orden})",
            '/operario/garantias'
        );
    }

    public static function garantiaCompletada(DevolucionGarantia $garantia): void
    {
        $pieza = $garantia->pieza;
        $orden = $garantia->orden;

        static::notificarRoles(
            ['Administrador', 'Recepcion'],
            'garantia_completada',
            'Garantia completada',
            "Trabajo de garantia completado para pieza '{$pieza->nombre}' (Orden #{$orden->numero_orden})",
            "/recepcion/ordenes/{$orden->id}"
        );
    }

    public static function garantiaReentregada(DevolucionGarantia $garantia): void
    {
        if (!$garantia->cobrable) {
            return;
        }

        $pieza = $garantia->pieza;
        $orden = $garantia->orden;
        $monto = number_format($garantia->monto_cobro, 0, '.', ',');

        static::notificarRoles(
            ['Contabilidad'],
            'garantia_reentregada',
            'Garantia cobrable reentregada',
            "Garantia cobrable (\${$monto}) reentregada - pieza '{$pieza->nombre}' (Orden #{$orden->numero_orden})",
            "/recepcion/ordenes/{$orden->id}"
        );
    }

    // ──────────────────────────────────────────
    // Pagos
    // ──────────────────────────────────────────

    public static function abonoPendienteAprobacion(Pago $pago, Orden $orden): void
    {
        $monto = number_format($pago->monto, 0, '.', ',');

        static::notificarRoles(
            ['Administrador', 'Contabilidad'],
            'abono_pendiente_aprobacion',
            'Pago pendiente de aprobacion',
            "Pago de \${$monto} registrado en Orden #{$orden->numero_orden} - pendiente de aprobacion",
            '/contabilidad/pagos-pendientes'
        );
    }

    public static function pagoAprobado(Pago $pago): void
    {
        if (!$pago->registrado_por) {
            return;
        }

        $monto = number_format($pago->monto, 0, '.', ',');
        $orden = $pago->orden;

        static::notificar(
            User::find($pago->registrado_por),
            'pago_aprobado',
            'Pago aprobado',
            "Tu pago de \${$monto} en Orden #{$orden->numero_orden} fue aprobado",
            "/recepcion/ordenes/{$orden->id}"
        );
    }

    public static function pagoRechazado(Pago $pago, string $ordenNumero, int $ordenId): void
    {
        if (!$pago->registrado_por) {
            return;
        }

        $monto = number_format($pago->monto, 0, '.', ',');

        static::notificar(
            User::find($pago->registrado_por),
            'pago_rechazado',
            'Pago rechazado',
            "Tu pago de \${$monto} en Orden #{$ordenNumero} fue rechazado",
            "/recepcion/ordenes/{$ordenId}"
        );
    }

    // ──────────────────────────────────────────
    // Ordenes
    // ──────────────────────────────────────────

    public static function ordenGenerada(Orden $orden, int $operarioId): void
    {
        static::notificar(
            User::find($operarioId),
            'orden_generada',
            'Nueva orden asignada',
            "Se te asigno la Orden #{$orden->numero_orden}",
            "/operario/ordenes/{$orden->id}"
        );
    }

    public static function piezaCompletada(OrdenPieza $pieza, User $operario): void
    {
        $orden = $pieza->orden;

        static::notificarRoles(
            ['Administrador', 'Recepcion'],
            'pieza_completada',
            'Pieza completada al 100%',
            "'{$pieza->nombre}' completada por {$operario->name} (Orden #{$orden->numero_orden})",
            "/recepcion/ordenes/{$orden->id}"
        );
    }

    // ──────────────────────────────────────────
    // Borradores
    // ──────────────────────────────────────────

    public static function borradorExpirando(Orden $orden, int $diasRestantes): void
    {
        if (!$orden->creado_por) {
            return;
        }

        $cliente = $orden->cliente->nombre ?? 'Sin cliente';
        $label = $diasRestantes === 1 ? '1 dia' : "{$diasRestantes} dias";

        static::notificar(
            User::find($orden->creado_por),
            'borrador_expirando',
            'Borrador proximo a expirar',
            "Tu borrador (ID #{$orden->id}, Cliente: {$cliente}) sera eliminado en {$label} por inactividad",
            "/recepcion/ordenes/{$orden->id}/editar"
        );
    }
}

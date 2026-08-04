<?php

namespace App\Services;

use App\Models\ConfiguracionSistema;
use App\Models\Notificacion;
use App\Models\Orden;
use App\Models\User;
use App\Services\Auth\RoleService;

class BloqueoService
{
    protected RoleService $roleService;

    public function __construct(RoleService $roleService)
    {
        $this->roleService = $roleService;
    }

    /**
     * Intenta bloquear una orden para el usuario.
     */
    public function bloquear(Orden $orden, User $user): array
    {
        // Verificar si ya esta bloqueada
        if ($orden->bloqueada_por) {
            // Auto-liberar si expiro el timeout
            if ($this->haExpirado($orden)) {
                $this->liberarBloqueo($orden);
            } else {
                // Bloqueada por otro usuario
                if ((int) $orden->bloqueada_por !== $user->id) {
                    $bloqueador = User::find($orden->bloqueada_por);

                    // Dos operarios pueden trabajar simultaneamente en la misma orden
                    // porque cada uno edita solo sus piezas asignadas (no hay conflicto)
                    if ($user->isOperario() && $bloqueador && $bloqueador->isOperario()) {
                        return ['success' => true, 'shared' => true];
                    }

                    $jerarquiaUser = $this->roleService->getJerarquia($user);
                    $jerarquiaBloqueador = $this->roleService->getJerarquia($bloqueador);

                    return [
                        'success' => false,
                        'locked_by' => $bloqueador->name,
                        'locked_by_role' => $bloqueador->getRoleNames()->first() ?? '',
                        'can_force_close' => $jerarquiaUser > $jerarquiaBloqueador,
                    ];
                }

                // Ya bloqueada por el mismo usuario, renovar
                $orden->update(['bloqueada_en' => now()]);
                return ['success' => true, 'renewed' => true];
            }
        }

        // Adquirir bloqueo
        $orden->update([
            'bloqueada_por' => $user->id,
            'bloqueada_en' => now(),
        ]);

        return ['success' => true];
    }

    /**
     * Desbloquea una orden.
     */
    public function desbloquear(Orden $orden, User $user): bool
    {
        if (!$orden->bloqueada_por) {
            return true;
        }

        // Solo puede desbloquear el dueño del lock o alguien de mayor jerarquia
        if ((int) $orden->bloqueada_por === $user->id) {
            $this->liberarBloqueo($orden);
            return true;
        }

        // Operario compartiendo: no liberar el lock del otro operario, solo "salir" silenciosamente
        $bloqueador = User::find($orden->bloqueada_por);
        if ($user->isOperario() && $bloqueador && $bloqueador->isOperario()) {
            return true;
        }

        if ($bloqueador && $this->roleService->getJerarquia($user) > $this->roleService->getJerarquia($bloqueador)) {
            $this->liberarBloqueo($orden);
            return true;
        }

        return false;
    }

    /**
     * Renueva el bloqueo (heartbeat).
     */
    public function renovarBloqueo(Orden $orden, User $user): bool
    {
        if ((int) $orden->bloqueada_por === $user->id) {
            $orden->update(['bloqueada_en' => now()]);
            return true;
        }

        // Operario compartiendo orden con otro operario: renovar el bloqueo del titular
        // para que no expire mientras ambos trabajan
        $bloqueador = User::find($orden->bloqueada_por);
        if ($user->isOperario() && $bloqueador && $bloqueador->isOperario()) {
            $orden->update(['bloqueada_en' => now()]);
            return true;
        }

        return false;
    }

    /**
     * Verifica el estado de bloqueo de una orden.
     */
    public function verificarBloqueo(Orden $orden): array
    {
        if (!$orden->bloqueada_por) {
            return ['locked' => false];
        }

        // Auto-liberar si expiro
        if ($this->haExpirado($orden)) {
            $this->liberarBloqueo($orden);
            return ['locked' => false, 'expired' => true];
        }

        $bloqueador = User::find($orden->bloqueada_por);

        $timeoutForzar = ConfiguracionSistema::get('timeout_forzar_cierre', 15);

        // Limpiar notificaciones de forzar_cierre obsoletas (que quedaron colgadas
        // sin marcarse como leidas porque el operario cerro la pagina antes del countdown).
        Notificacion::where('usuario_id', $orden->bloqueada_por)
            ->where('tipo', 'forzar_cierre')
            ->where('leida', false)
            ->where('url', 'like', "%ordenes/{$orden->id}%")
            ->where('created_at', '<', now()->subSeconds($timeoutForzar))
            ->update(['leida' => true, 'leida_en' => now()]);

        // Verificar si hay notificacion de forzar cierre pendiente y vigente
        $forceClose = Notificacion::where('usuario_id', $orden->bloqueada_por)
            ->where('tipo', 'forzar_cierre')
            ->where('leida', false)
            ->where('url', 'like', "%ordenes/{$orden->id}%")
            ->where('created_at', '>=', now()->subSeconds($timeoutForzar))
            ->latest()
            ->first();

        $result = [
            'locked' => true,
            'locked_by_id' => $orden->bloqueada_por,
            'locked_by' => $bloqueador ? $bloqueador->name : 'Desconocido',
            'locked_by_role' => $bloqueador ? ($bloqueador->getRoleNames()->first() ?? '') : '',
            'locked_since' => $orden->bloqueada_en?->format('H:i'),
            'force_close' => false,
        ];

        if ($forceClose) {
            $segundosTranscurridos = now()->diffInSeconds($forceClose->created_at);
            $segundosRestantes = max(0, $timeoutForzar - $segundosTranscurridos);

            $result['force_close'] = true;
            $result['force_close_seconds_remaining'] = $segundosRestantes;

            // Si ya paso el timeout, forzar cierre automatico
            if ($segundosRestantes <= 0) {
                $this->liberarBloqueo($orden);
                $forceClose->update(['leida' => true, 'leida_en' => now()]);
                $result['locked'] = false;
                $result['force_closed'] = true;
            }
        }

        return $result;
    }

    /**
     * Inicia un forzar cierre de la orden bloqueada.
     * El usuario superior solicita que el operario cierre.
     */
    public function forzarCierre(Orden $orden, User $solicitante): array
    {
        if (!$orden->bloqueada_por) {
            return ['success' => false, 'error' => 'La orden no esta bloqueada.'];
        }

        $bloqueador = User::find($orden->bloqueada_por);
        if (!$bloqueador) {
            $this->liberarBloqueo($orden);
            return ['success' => true, 'immediate' => true];
        }

        // Verificar jerarquia
        if ($this->roleService->getJerarquia($solicitante) <= $this->roleService->getJerarquia($bloqueador)) {
            return ['success' => false, 'error' => 'No tienes suficiente jerarquia para forzar el cierre.'];
        }

        $timeout = ConfiguracionSistema::get('timeout_forzar_cierre', 15);

        // Crear notificacion para el operario
        Notificacion::create([
            'usuario_id' => $bloqueador->id,
            'tipo' => 'forzar_cierre',
            'titulo' => 'Cierre de orden requerido',
            'contenido' => "{$solicitante->name} necesita editar la Orden #{$orden->numero_orden}. Se cerrara automaticamente en {$timeout} segundos.",
            'url' => "/operario/ordenes/{$orden->id}",
            'leida' => false,
        ]);

        return [
            'success' => true,
            'timeout' => $timeout,
            'operario' => $bloqueador->name,
        ];
    }

    /**
     * Verifica si un usuario puede desplazar a otro segun jerarquia.
     */
    public function puedeDesplazar(User $solicitante, User $bloqueador): bool
    {
        return $this->roleService->getJerarquia($solicitante) > $this->roleService->getJerarquia($bloqueador);
    }

    /**
     * Verifica si el bloqueo ha expirado por inactividad.
     * Desactivado: los bloqueos no expiran automaticamente.
     * Solo se liberan por: desbloqueo manual, forzar cierre, o cierre de sesion de trabajo.
     */
    protected function haExpirado(Orden $orden): bool
    {
        if (!$orden->bloqueada_por || !$orden->bloqueada_en) {
            return false;
        }

        $bloqueador = User::find($orden->bloqueada_por);

        // Los bloqueos de OPERARIO no expiran por inactividad (se liberan al salir,
        // por forzar cierre o cierre de la sesion de trabajo).
        if (!$bloqueador || $bloqueador->isOperario()) {
            return false;
        }

        // Los bloqueos de edicion de Recepcion/Admin/Contabilidad SI expiran por
        // inactividad, para no dejar la orden trabada si cierran la pestana sin liberar.
        // El autoguardado renueva el bloqueo, asi que solo expira si de verdad se abandono.
        $minutos = max(5, (int) ConfiguracionSistema::get('timeout_autoguardado_recepcion', 5) * 2);
        return $orden->bloqueada_en->lt(now()->subMinutes($minutos));
    }

    /**
     * Libera el bloqueo de la orden.
     */
    protected function liberarBloqueo(Orden $orden): void
    {
        $orden->update([
            'bloqueada_por' => null,
            'bloqueada_en' => null,
        ]);
    }
}

<nav class="sidebar-nav">
    {{-- Dashboard (todos) --}}
    <div class="nav-item {{ request()->routeIs('dashboard') || request()->routeIs('recepcion.panel') || request()->routeIs('operario.panel') || request()->routeIs('contabilidad.panel') || request()->routeIs('admin.panel') ? 'active' : '' }}">
        <a href="{{ route('dashboard') }}" class="nav-link">
            <i class="bi bi-house-door"></i>
            <span>Inicio</span>
        </a>
    </div>

    {{-- SECCION ENTREGAS (todos los roles) --}}
    <div class="nav-section-title">Entregas</div>

    <div class="nav-item {{ request()->routeIs('recepcion.entregas-pendientes') || (request()->routeIs('recepcion.entregas.*') && !request()->routeIs('recepcion.entregas.historial')) ? 'active' : '' }}">
        <a href="{{ route('recepcion.entregas-pendientes') }}" class="nav-link">
            <i class="bi bi-box-seam"></i>
            <span>Entregas Pendientes</span>
        </a>
    </div>

    <div class="nav-item {{ request()->routeIs('recepcion.entregas.historial') ? 'active' : '' }}">
        <a href="{{ route('recepcion.entregas.historial') }}" class="nav-link">
            <i class="bi bi-clock-history"></i>
            <span>Historial Entregas</span>
        </a>
    </div>

    @hasanyrole('Administrador|Recepcion')
        {{-- SECCION ORDENES --}}
        <div class="nav-section-title">Ordenes</div>

        <div class="nav-item {{ request()->routeIs('recepcion.ordenes.crear') ? 'active' : '' }}">
            <a href="{{ route('recepcion.ordenes.crear') }}" class="nav-link">
                <i class="bi bi-file-earmark-plus"></i>
                <span>Crear Orden</span>
            </a>
        </div>

        <div class="nav-item {{ request()->routeIs('recepcion.ordenes.index') || request()->routeIs('recepcion.ordenes.show') || request()->routeIs('recepcion.ordenes.edit') ? 'active' : '' }}">
            <a href="{{ route('recepcion.ordenes.index') }}" class="nav-link">
                <i class="bi bi-search"></i>
                <span>Buscar Ordenes</span>
            </a>
        </div>

        <div class="nav-item {{ request()->routeIs('recepcion.garantias.*') ? 'active' : '' }}">
            <a href="{{ route('recepcion.garantias.index') }}" class="nav-link">
                <i class="bi bi-shield-check"></i>
                <span>Devoluciones/Garantias</span>
            </a>
        </div>

        {{-- SECCION CATALOGOS --}}
        <div class="nav-section-title">Catalogos</div>

        <div class="nav-item {{ request()->routeIs('recepcion.clientes.*') ? 'active' : '' }}">
            <a href="{{ route('recepcion.clientes.index') }}" class="nav-link">
                <i class="bi bi-person-lines-fill"></i>
                <span>Clientes</span>
            </a>
        </div>

        <div class="nav-item {{ request()->routeIs('recepcion.items.*') ? 'active' : '' }}">
            <a href="{{ route('recepcion.items.index') }}" class="nav-link">
                <i class="bi bi-tags"></i>
                <span>Items</span>
            </a>
        </div>

        <div class="nav-item {{ request()->routeIs('recepcion.bosquejos-matriz.*') ? 'active' : '' }}">
            <a href="{{ route('recepcion.bosquejos-matriz.index') }}" class="nav-link">
                <i class="bi bi-image"></i>
                <span>Bosquejos Matriz</span>
            </a>
        </div>

        <div class="nav-item {{ request()->routeIs('recepcion.consulta-precios.*') ? 'active' : '' }}">
            <a href="{{ route('recepcion.consulta-precios.index') }}" class="nav-link">
                <i class="bi bi-calculator"></i>
                <span>Consulta Precios</span>
            </a>
        </div>
    @endhasanyrole

    @can('ver_bosquejos_matriz')
        @unless(auth()->user()->hasAnyRole(['Administrador', 'Recepcion']))
        <div class="nav-section-title">Catalogos</div>
        <div class="nav-item {{ request()->routeIs('recepcion.bosquejos-matriz.*') ? 'active' : '' }}">
            <a href="{{ route('recepcion.bosquejos-matriz.index') }}" class="nav-link">
                <i class="bi bi-image"></i>
                <span>Bosquejos Matriz</span>
            </a>
        </div>
        @endunless
    @endcan

    @role('Operario')
        {{-- SECCION ORDENES (solo lectura para Operario) --}}
        <div class="nav-section-title">Ordenes</div>

        <div class="nav-item {{ request()->routeIs('recepcion.ordenes.index') || request()->routeIs('recepcion.ordenes.show') ? 'active' : '' }}">
            <a href="{{ route('recepcion.ordenes.index') }}" class="nav-link">
                <i class="bi bi-search"></i>
                <span>Ordenes</span>
            </a>
        </div>

        {{-- SECCION MI TRABAJO --}}
        <div class="nav-section-title">Mi Trabajo</div>

        <div class="nav-item {{ request()->routeIs('operario.ordenes-asignadas') || request()->routeIs('operario.ordenes.*') ? 'active' : '' }}">
            <a href="{{ route('operario.ordenes-asignadas') }}" class="nav-link">
                <i class="bi bi-list-check"></i>
                <span>Ordenes Asignadas</span>
            </a>
        </div>

        <div class="nav-item {{ request()->routeIs('operario.complementar') ? 'active' : '' }}">
            <a href="{{ route('operario.complementar') }}" class="nav-link">
                <i class="bi bi-plus-circle"></i>
                <span>Ordenes Pendientes por Terminar</span>
            </a>
        </div>

        <div class="nav-item {{ request()->routeIs('operario.garantias') ? 'active' : '' }}">
            <a href="{{ route('operario.garantias') }}" class="nav-link">
                <i class="bi bi-shield-exclamation"></i>
                <span>Garantias Asignadas</span>
            </a>
        </div>
    @endrole

    @role('Contabilidad')
        {{-- SECCION ORDENES (solo lectura para Contabilidad) --}}
        <div class="nav-section-title">Ordenes</div>

        <div class="nav-item {{ request()->routeIs('recepcion.ordenes.index') || request()->routeIs('recepcion.ordenes.show') ? 'active' : '' }}">
            <a href="{{ route('recepcion.ordenes.index') }}" class="nav-link">
                <i class="bi bi-search"></i>
                <span>Buscar Ordenes</span>
            </a>
        </div>

        {{-- SECCION CATALOGOS (solo Clientes para Contabilidad) --}}
        <div class="nav-section-title">Catalogos</div>

        <div class="nav-item {{ request()->routeIs('recepcion.clientes.*') ? 'active' : '' }}">
            <a href="{{ route('recepcion.clientes.index') }}" class="nav-link">
                <i class="bi bi-person-lines-fill"></i>
                <span>Clientes</span>
            </a>
        </div>
    @endrole

    @hasanyrole('Administrador|Contabilidad')
        {{-- SECCION FINANZAS --}}
        <div class="nav-section-title">Finanzas</div>

        <div class="nav-item {{ request()->routeIs('contabilidad.ordenes-pendientes') ? 'active' : '' }}">
            <a href="{{ route('contabilidad.ordenes-pendientes') }}" class="nav-link">
                <i class="bi bi-cash-coin"></i>
                <span>O. Pendientes Pagar</span>
            </a>
        </div>

        <div class="nav-item {{ request()->routeIs('contabilidad.historial-financiero') ? 'active' : '' }}">
            <a href="{{ route('contabilidad.historial-financiero') }}" class="nav-link">
                <i class="bi bi-journal-text"></i>
                <span>Historial Financiero</span>
            </a>
        </div>

        <div class="nav-item {{ request()->routeIs('contabilidad.pagos-pendientes') ? 'active' : '' }}">
            <a href="{{ route('contabilidad.pagos-pendientes') }}" class="nav-link">
                <i class="bi bi-hourglass-split"></i>
                <span>Pagos por Aprobar</span>
            </a>
        </div>

        <div class="nav-item {{ request()->routeIs('contabilidad.reporte-items') ? 'active' : '' }}">
            <a href="{{ route('contabilidad.reporte-items') }}" class="nav-link">
                <i class="bi bi-bar-chart-line"></i>
                <span>Reporte Ventas por Categoría de Items</span>
            </a>
        </div>

        @role('Contabilidad')
        <div class="nav-item {{ request()->routeIs('contabilidad.items.*') ? 'active' : '' }}">
            <a href="{{ route('contabilidad.items.index') }}" class="nav-link">
                <i class="bi bi-tags"></i>
                <span>Items</span>
            </a>
        </div>
        @endrole
    @endhasanyrole

    @role('Administrador')
        {{-- SECCION ADMIN --}}
        <div class="nav-section-title">Administracion</div>

        <div class="nav-item {{ request()->routeIs('admin.usuarios.*') ? 'active' : '' }}">
            <a href="{{ route('admin.usuarios.index') }}" class="nav-link">
                <i class="bi bi-people"></i>
                <span>Usuarios</span>
            </a>
        </div>

        <div class="nav-item {{ request()->routeIs('admin.configuracion') ? 'active' : '' }}">
            <a href="{{ route('admin.configuracion') }}" class="nav-link">
                <i class="bi bi-gear"></i>
                <span>Configuracion</span>
            </a>
        </div>

        <div class="nav-item {{ request()->routeIs('admin.tabla-precios.*') ? 'active' : '' }}">
            <a href="{{ route('admin.tabla-precios.index') }}" class="nav-link">
                <i class="bi bi-table"></i>
                <span>Tabla de Precios</span>
            </a>
        </div>
    @endrole

    {{-- SECCION SISTEMA (todos) --}}
    <div class="nav-section-title">Sistema</div>

    @php
        $actRoute = auth()->user()->hasRole('Administrador') ? 'admin.actividades'
            : (auth()->user()->hasRole('Recepcion') ? 'recepcion.actividades'
            : (auth()->user()->hasRole('Contabilidad') ? 'contabilidad.actividades'
            : 'operario.actividades'));
    @endphp

    <div class="nav-item {{ request()->routeIs('*.actividades') ? 'active' : '' }}">
        <a href="{{ route($actRoute) }}" class="nav-link">
            <i class="bi bi-clock-history"></i>
            <span>Mis Actividades</span>
        </a>
    </div>

    @hasanyrole('Administrador|Recepcion')
    @php
        $actGlobalRoute = auth()->user()->hasRole('Administrador')
            ? 'admin.actividades-globales' : 'recepcion.actividades-globales';
    @endphp
    <div class="nav-item {{ request()->routeIs('*.actividades-globales') ? 'active' : '' }}">
        <a href="{{ route($actGlobalRoute) }}" class="nav-link">
            <i class="bi bi-activity"></i>
            <span>Actividades Globales</span>
        </a>
    </div>
    @endhasanyrole

    {{-- SECCION CUENTA --}}
    <div class="nav-section-title">Cuenta</div>

    <div class="nav-item {{ request()->routeIs('profile.edit') ? 'active' : '' }}">
        <a href="{{ route('profile.edit') }}" class="nav-link">
            <i class="bi bi-person-gear"></i>
            <span>Mi Perfil</span>
        </a>
    </div>
</nav>

<div class="sidebar-footer">
    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit" class="logout-btn">
            <i class="bi bi-box-arrow-left"></i>
            <span>Cerrar Sesion</span>
        </button>
    </form>
</div>

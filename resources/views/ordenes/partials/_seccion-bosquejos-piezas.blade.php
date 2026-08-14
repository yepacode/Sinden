{{-- Seccion 3: Piezas --}}
<style>
    /* Fondo de la vista (crear/editar orden) un poco mas oscuro que el blanco de las tarjetas */
    body:has(#ordenWizardApp) { background-color: #e6e8ec; }
    [data-bs-theme="dark"] body:has(#ordenWizardApp) { background-color: var(--sinden-gray-50); }

    /* Mensaje inline de confirmacion de pieza: color fuerte y animacion */
    #piezaMsgInline {
        background-color: #16a34a;
        border: 1px solid #15803d;
        color: #fff;
        font-weight: 600;
        box-shadow: 0 2px 8px rgba(22,163,74,.35);
    }
    #piezaMsgInline.pieza-msg-flash {
        animation: piezaMsgPop .35s ease-out;
    }
    @keyframes piezaMsgPop {
        0%   { transform: scale(.96); opacity: 0; }
        60%  { transform: scale(1.02); opacity: 1; }
        100% { transform: scale(1); opacity: 1; }
    }

    /* Divisor mas notable entre cada pieza */
    #tablaPiezas tbody tr.pieza-row td {
        border-top: 3px solid #94a3b8;
    }
    #tablaPiezas tbody tr.pieza-row:first-child td {
        border-top-width: 1px;
        border-top-color: #dee2e6;
    }
    [data-bs-theme="dark"] #tablaPiezas tbody tr.pieza-row td {
        border-top-color: #475569;
    }

    /* ===== Panel flotante de Bosquejos (modeless) ===== */
    .panel-bosquejos-flotante {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        height: 50vh;
        max-height: 50vh;
        z-index: 1045; /* debajo de los modales bootstrap (1055) pero sobre el contenido */
        background: #fff;
        border-bottom: 3px solid var(--bs-primary, #0d6efd);
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.25);
        display: flex;
        flex-direction: column;
    }
    [data-bs-theme="dark"] .panel-bosquejos-flotante {
        background: var(--sinden-gray-100, #1e293b);
        color: #e2e8f0;
    }
    .panel-bosquejos-header {
        flex: 0 0 auto;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.6rem 1rem;
        border-bottom: 1px solid #e9ecef;
        background: #f8f9fa;
    }
    [data-bs-theme="dark"] .panel-bosquejos-header {
        background: var(--sinden-gray-50, #0f172a);
        border-bottom-color: #334155;
    }
    .panel-bosquejos-body {
        flex: 1 1 auto;
        overflow-y: auto;
        -webkit-overflow-scrolling: touch;
        padding: 1rem;
    }
    .bosquejos-galeria {
        display: grid;
        grid-template-columns: repeat(var(--cols, 3), 1fr);
        gap: 0.75rem;
    }
    .bosquejo-galeria-item {
        border: 1px solid #e2e8f0;
        border-radius: 0.5rem;
        overflow: hidden;
        background: #fff;
        display: flex;
        flex-direction: column;
    }
    [data-bs-theme="dark"] .bosquejo-galeria-item {
        border-color: #334155;
        background: var(--sinden-gray-100, #1e293b);
    }
    .bosquejo-galeria-item img {
        width: 100%;
        aspect-ratio: 1 / 1;
        object-fit: contain;
        background: #f8f9fa;
        padding: 6px;
    }
    .bosquejo-galeria-item .bosquejo-galeria-caption {
        padding: 0.35rem 0.5rem;
        text-align: center;
        font-size: 0.8rem;
        border-top: 1px solid #f1f5f9;
        line-height: 1.2;
    }
    [data-bs-theme="dark"] .bosquejo-galeria-item .bosquejo-galeria-caption {
        border-top-color: #334155;
    }

    /* Resaltado breve de la pieza recien agregada (para ubicarla al vuelo tras el auto-scroll). */
    #tbodyPiezas tr.pieza-recien-agregada td {
        animation: piezaNuevaFlash 1.6s ease-out;
    }
    @keyframes piezaNuevaFlash {
        0%   { background-color: rgba(13,110,253,.28); }
        100% { background-color: transparent; }
    }
</style>
<div class="card border-0 shadow-sm mb-4 wizard-section" data-section="2" id="seccionBosquejosPiezas">
    <div class="card-header bg-white border-0 px-4 pt-4 pb-2">
        <h6 class="mb-0 fw-semibold">
            <i class="bi bi-puzzle me-2 text-primary"></i>2. Piezas
        </h6>
    </div>
    <div class="card-body px-4 pb-4 pt-2">

        {{-- Header: "Agregar Pieza" al CENTRO (mismo que el de abajo, para hallarlo siempre
             en la mitad). Importar Matriz a la derecha, titulo a la izquierda. --}}
        <div class="d-flex align-items-center mb-3">
            <div class="flex-fill">
                <h6 class="mb-0 fw-semibold text-secondary">
                    <i class="bi bi-puzzle me-1"></i> Piezas
                </h6>
            </div>
            <div class="flex-fill text-center">
                <button type="button" class="btn btn-sm btn-primary" onclick="agregarFilaPieza()">
                    <i class="bi bi-plus-lg me-1"></i> Agregar Pieza<span class="contador-piezas"></span>
                </button>
            </div>
            <div class="flex-fill text-end">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalBosquejoMatriz">
                    <i class="bi bi-grid-3x3 me-1"></i> Importar Matriz
                </button>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-bordered align-middle mb-0" id="tablaPiezas" style="display:none; min-width:750px;">
                <thead>
                    <tr class="table-light">
                        <th style="width:100px" class="text-center">Bosquejo</th>
                        <th style="width:40px" class="text-center">#</th>
                        <th style="width:120px">Identificador</th>
                        <th style="width:70px" class="text-center">Cantidad</th>
                        <th style="width:140px">Material</th>
                        <th style="width:90px">Calibre</th>
                        <th>Especificacion</th>
                        <th style="width:180px">Operario</th>
                        <th style="width:40px"></th>
                    </tr>
                </thead>
                <tbody id="tbodyPiezas">
                    {{-- Filas dinamicas via JS --}}
                </tbody>
            </table>
        </div>

        <div id="piezasVacio" class="text-center py-3 text-muted">
            <i class="bi bi-puzzle fs-3 d-block mb-1 opacity-50"></i>
            <small>No hay piezas. Si esta orden requiere trabajo sobre piezas, agregue al menos una.</small>
            <br><small class="text-info"><i class="bi bi-info-circle me-1"></i>Sin piezas = Venta directa (se marca como ejecutada al generar)</small>
        </div>

        {{-- Ver Bosquejos en ambas esquinas + "Agregar Pieza" al CENTRO (igual que arriba):
             al terminar de editar la ultima pieza se puede agregar otra sin subir. --}}
        <div class="mt-3 d-flex align-items-center">
            <div class="flex-fill">
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="abrirPanelBosquejos()">
                    <i class="bi bi-images me-1"></i> Ver Bosquejos
                </button>
            </div>
            <div class="flex-fill text-center">
                <button type="button" class="btn btn-sm btn-primary" onclick="agregarFilaPieza()">
                    <i class="bi bi-plus-lg me-1"></i> Agregar Pieza<span class="contador-piezas"></span>
                </button>
            </div>
            <div class="flex-fill text-end">
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="abrirPanelBosquejos()">
                    <i class="bi bi-images me-1"></i> Ver Bosquejos
                </button>
            </div>
        </div>

        {{-- Divs ocultos para compatibilidad con JS residual --}}
        <div id="bosquejosGrid" style="display:none;"></div>
        <div id="bosquejosVacio" style="display:none;"></div>

    </div>
</div>

{{-- Panel flotante de Bosquejos (modeless: no bloquea el formulario detras) --}}
<div id="panelBosquejos" class="panel-bosquejos-flotante" style="display:none;">
    <div class="panel-bosquejos-header">
        <h6 class="mb-0 fw-semibold">
            <i class="bi bi-images me-2 text-primary"></i>Bosquejos
            <span class="badge bg-light text-muted border ms-1" id="panelBosquejosCount">0</span>
        </h6>
        <div class="d-flex align-items-center gap-2 ms-auto">
            <span class="small text-muted d-none d-sm-inline">Por fila:</span>
            <div class="btn-group btn-group-sm" role="group" id="panelBosquejosColsGroup">
                <button type="button" class="btn btn-outline-secondary" onclick="setBosquejosPorFila(1)" data-cols="1">1</button>
                <button type="button" class="btn btn-outline-secondary" onclick="setBosquejosPorFila(2)" data-cols="2">2</button>
                <button type="button" class="btn btn-outline-secondary" onclick="setBosquejosPorFila(3)" data-cols="3">3</button>
                <button type="button" class="btn btn-outline-secondary" onclick="setBosquejosPorFila(4)" data-cols="4">4</button>
                <button type="button" class="btn btn-outline-secondary active" onclick="setBosquejosPorFila(6)" data-cols="6">6</button>
            </div>
            <button type="button" class="btn-close ms-1" aria-label="Cerrar" onclick="cerrarPanelBosquejos()"></button>
        </div>
    </div>
    <div class="panel-bosquejos-body" id="panelBosquejosBody">
        <div class="bosquejos-galeria" id="galeriaBosquejos" style="--cols:6;"></div>
    </div>
</div>

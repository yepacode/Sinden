{{-- Seccion 3: Fechas y Notas --}}
<div class="card border-0 shadow-sm mt-3">
    <div class="card-header bg-white border-0 px-4 pt-3 pb-0">
        <h6 class="mb-0 fw-semibold"><i class="bi bi-calendar3 me-2 text-primary"></i>Fechas y Notas</h6>
    </div>
    <div class="card-body px-4 pb-3 pt-2">
        <div class="row">
            <div class="col-md-3">
                <small class="text-muted d-block">Fecha Creacion</small>
                <span class="fw-medium">{{ $orden->created_at ? $orden->created_at->format('d/m/Y H:i') : '-' }}</span>
            </div>
            <div class="col-md-3">
                <small class="text-muted d-block">Fecha Entrega</small>
                <span class="fw-medium">{{ $orden->fecha_entrega ? $orden->fecha_entrega->format('d/m/Y') : '-' }}</span>
            </div>
            <div class="col-md-3">
                <small class="text-muted d-block">Hora Entrega</small>
                <span class="fw-medium">{{ $orden->hora_entrega_fmt ?? '-' }}</span>
            </div>
            <div class="col-md-3">
                <small class="text-muted d-block">Creado por</small>
                <span class="fw-medium">{{ $orden->creador->name ?? '-' }}</span>
            </div>
        </div>
        @if($orden->notas)
            <div class="mt-3 pt-2 border-top">
                <small class="text-muted d-block mb-1">Notas / Observaciones Generales</small>
                <p class="mb-0">{{ $orden->notas }}</p>
            </div>
        @endif
    </div>
</div>

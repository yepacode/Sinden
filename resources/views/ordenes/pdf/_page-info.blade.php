{{-- PAGINA 1: Informacion de la Orden --}}
<div class="page-con-margen">

{{-- Header: Logo + Titulo --}}
<table class="pdf-header-table">
    <tr>
        <td style="width: 40%;">
            @if($logoBase64)
                <img src="{{ $logoBase64 }}" class="company-logo">
            @endif
        </td>
        <td style="width: 60%; text-align: right;">
            @php $esBorrador = $orden->estado_trabajo === 'borrador'; @endphp
            <div class="orden-title">{{ $esBorrador ? 'COTIZACIÓN' : 'ORDEN DE TRABAJO' }}</div>
            <div class="orden-numero">{{ $orden->numero_orden ?? 'COTIZACIÓN #' . $orden->id }}</div>
            <div style="font-size: 9px; color: #6b7280; margin-top: 3px;">
                @php
                    $labelTrabajo = [
                        'borrador' => 'BORRADOR', 'generada' => 'GENERADA', 'en_ejecucion' => 'EN EJECUCION',
                        'ejecutada_parcialmente' => 'EJEC. PARCIAL', 'ejecutada' => 'EJECUTADA', 'anulada' => 'ANULADA',
                    ];
                @endphp
                <span class="estado-badge estado-{{ $orden->estado_trabajo }}">
                    {{ $labelTrabajo[$orden->estado_trabajo] ?? strtoupper($orden->estado_trabajo) }}
                </span>
                @if($orden->estado_entrega)
                    @php
                        $labelEntrega = ['entregada_parcialmente' => 'ENTREGA PARCIAL', 'entregada' => 'ENTREGADA'];
                    @endphp
                    <span class="estado-badge estado-{{ $orden->estado_entrega === 'entregada' ? 'entregada' : 'ejecutada_parcialmente' }}">
                        {{ $labelEntrega[$orden->estado_entrega] ?? strtoupper($orden->estado_entrega) }}
                    </span>
                @endif
                @if($orden->estado_pago)
                    <span class="estado-badge {{ $orden->estado_pago === 'pagado' ? 'estado-ejecutada' : 'estado-anulada' }}">
                        {{ $orden->estado_pago === 'pagado' ? 'PAGADO' : 'SALDO PEND.' }}
                    </span>
                @endif
            </div>
        </td>
    </tr>
</table>

{{-- Cliente --}}
<div class="section">
    <div class="section-title">CLIENTE</div>
    <table class="info-table">
        <tr>
            <td class="info-label">Nombre:</td>
            <td>{{ $orden->cliente->nombre ?? '-' }}</td>
            <td class="info-label">Cedula/NIT:</td>
            <td>{{ $orden->cliente->cedula ?? '-' }}</td>
        </tr>
        <tr>
            <td class="info-label">Direccion:</td>
            <td>{{ $orden->cliente->direccion ?? '-' }}</td>
            <td class="info-label">Correo:</td>
            <td>{{ $orden->cliente->correo ?? '-' }}</td>
        </tr>
        <tr>
            <td class="info-label">Celular 1:</td>
            <td>{{ $orden->cliente->celular_1 ?? '-' }}</td>
            <td class="info-label">Celular 2:</td>
            <td>{{ $orden->cliente->celular_2 ?? '-' }}</td>
        </tr>
    </table>
</div>

{{-- Fechas --}}
<div class="section">
    <div class="section-title">FECHAS</div>
    <table class="info-table">
        <tr>
            <td class="info-label">Creacion:</td>
            <td>{{ $orden->created_at ? $orden->created_at->timezone('America/Bogota')->format('d/m/Y H:i') : '-' }}</td>
            <td class="info-label">Entrega:</td>
            <td>
                {{ $orden->fecha_entrega ? $orden->fecha_entrega->format('d/m/Y') : '-' }}
                @if($orden->hora_entrega)
                    a las {{ $orden->hora_entrega }}
                @endif
            </td>
        </tr>
        <tr>
            <td class="info-label">Creado por:</td>
            <td>{{ $orden->creador->name ?? '-' }}</td>
            <td></td>
            <td></td>
        </tr>
    </table>
    @if($orden->notas)
        <div style="margin-top: 5px;">
            <span class="info-label" style="font-size: 9px;">Notas: </span>
            <span style="font-size: 9px;">{{ $orden->notas }}</span>
        </div>
    @endif
</div>

{{-- Items --}}
<div class="section">
    <div class="section-title">ITEMS (PRODUCTOS Y SERVICIOS)</div>
    @if($orden->items->count() > 0)
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 25px;">#</th>
                    <th style="width: 60px;">Codigo</th>
                    <th>Descripcion</th>
                    <th class="text-center" style="width: 35px;">Cant.</th>
                    <th class="text-end" style="width: 70px;">P.Unitario</th>
                    <th class="text-center" style="width: 35px;">IVA%</th>
                    <th class="text-center" style="width: 40px;">Desc.%</th>
                    <th class="text-end" style="width: 70px;">Subtotal</th>
                    <th class="text-end" style="width: 70px;">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($orden->items as $i => $item)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td>{{ $item->codigo ?? '-' }}</td>
                        <td>{{ $item->descripcion }}</td>
                        <td class="text-center">{{ \App\Helpers\Format::cantidad($item->cantidad) }}</td>
                        <td class="text-end">${{ number_format($item->precio_unitario, 0, ',', '.') }}</td>
                        <td class="text-center">{{ number_format($item->porcentaje_iva, 0) }}%</td>
                        <td class="text-center">{{ $item->descuento_porcentaje > 0 ? number_format($item->descuento_porcentaje, 2) . '%' : '-' }}</td>
                        <td class="text-end">${{ number_format($item->subtotal, 0, ',', '.') }}</td>
                        <td class="text-end fw-semibold">${{ number_format($item->total, 0, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
            @php
                $totalDescuentosPdf = $orden->items->sum('descuento_monto');
                $totalAntesDescuentoPdf = $orden->subtotal + $orden->monto_iva;
            @endphp
            <tfoot>
                <tr>
                    <td colspan="7"></td>
                    <td class="text-end text-muted" style="border-top: 1px solid #d1d5db;">Subtotal bruto</td>
                    <td class="text-end fw-semibold" style="border-top: 1px solid #d1d5db;">${{ number_format($orden->subtotal, 0, ',', '.') }}</td>
                </tr>
                <tr>
                    <td colspan="7"></td>
                    <td class="text-end text-muted">IVA</td>
                    <td class="text-end fw-semibold">${{ number_format($orden->monto_iva, 0, ',', '.') }}</td>
                </tr>
                <tr>
                    <td colspan="7"></td>
                    <td class="text-end fw-bold">TOTAL</td>
                    <td class="text-end fw-bold">${{ number_format($totalAntesDescuentoPdf, 0, ',', '.') }}</td>
                </tr>
                @if($totalDescuentosPdf > 0)
                    <tr>
                        <td colspan="7"></td>
                        <td class="text-end" style="color:#b91c1c;">Descuento</td>
                        <td class="text-end fw-semibold" style="color:#b91c1c;">-${{ number_format($totalDescuentosPdf, 0, ',', '.') }}</td>
                    </tr>
                @endif
                <tr class="total-row">
                    <td colspan="7"></td>
                    <td class="text-end fw-bold">Total con retenciones</td>
                    <td class="text-end fw-bold" style="font-size: 11px;">${{ number_format($orden->total, 0, ',', '.') }}</td>
                </tr>
            </tfoot>
        </table>
    @else
        <p class="text-muted">No hay items registrados.</p>
    @endif
</div>

{{-- Pagos --}}
<div class="section">
    <div class="section-title">PAGOS</div>
    @if($pagosAprobados->count() > 0)
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 70px;">Fecha</th>
                    <th class="text-end" style="width: 80px;">Monto</th>
                    <th style="width: 70px;">Metodo</th>
                    <th>Referencia</th>
                    <th style="width: 100px;">Registrado por</th>
                </tr>
            </thead>
            <tbody>
                @foreach($pagosAprobados->sortByDesc('created_at') as $pago)
                    <tr>
                        <td>{{ $pago->created_at->timezone('America/Bogota')->format('d/m/Y') }}</td>
                        <td class="text-end fw-semibold">${{ number_format($pago->monto, 0, ',', '.') }}</td>
                        <td>{{ ($tiposPagoMapa ?? [])[$pago->metodo_pago]['etiqueta'] ?? (($tiposPagoMapa ?? [])[$pago->metodo_pago]['nombre'] ?? ucfirst($pago->metodo_pago)) }}</td>
                        <td>{{ $pago->referencia_pago ?? '-' }}</td>
                        <td>{{ $pago->registradoPorUsuario->name ?? '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p class="text-muted" style="font-size: 9px;">No hay pagos registrados.</p>
    @endif

    {{-- Resumen financiero --}}
    <table style="width: 250px; margin-left: auto; margin-top: 8px; font-size: 9px;">
        <tr>
            <td style="padding: 2px 5px;">Total</td>
            <td class="text-end fw-bold" style="padding: 2px 5px;">${{ number_format($orden->total, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td style="padding: 2px 5px;">Pagado</td>
            <td class="text-end fw-semibold text-success" style="padding: 2px 5px;">${{ number_format($orden->total_pagado, 0, ',', '.') }}</td>
        </tr>
        <tr style="border-top: 2px solid #4A7C59;">
            <td class="fw-bold" style="padding: 3px 5px;">Saldo</td>
            <td class="text-end fw-bold {{ $orden->saldo > 0 ? 'text-danger' : 'text-success' }}" style="padding: 3px 5px; font-size: 11px;">
                ${{ number_format($orden->saldo, 0, ',', '.') }}
            </td>
        </tr>
    </table>
</div>

{{-- Firma del cliente --}}
@if($firmaBase64)
    <div class="section">
        <div class="section-title">FIRMA DEL CLIENTE</div>
        <img src="{{ $firmaBase64 }}" class="firma-img">
    </div>
@endif

{{-- Pie de pagina --}}
<div class="pdf-footer">
    <table style="width: 100%; font-size: 8px; color: #9ca3af;">
        <tr>
            <td>Generado por: {{ $generadoPor }} | {{ $fechaGeneracion }}</td>
            <td class="text-end">Creado por: {{ $orden->creador->name ?? '-' }}</td>
        </tr>
    </table>
</div>

</div>{{-- cierre page-con-margen --}}

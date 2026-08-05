{{-- Seccion 4: Items y Totales --}}
<div class="card border-0 shadow-sm mt-3">
    <div class="card-header bg-white border-0 px-4 pt-3 pb-0">
        <h6 class="mb-0 fw-semibold"><i class="bi bi-receipt me-2 text-primary"></i>Items (Productos y servicios)</h6>
    </div>
    <div class="card-body px-4 pb-3 pt-2">
        @if($orden->items->count() > 0)
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr class="text-muted small">
                            <th>#</th>
                            <th>Codigo</th>
                            <th>Descripcion</th>
                            <th class="text-center">Cant.</th>
                            <th class="text-end">P.Unitario</th>
                            <th class="text-center">IVA%</th>
                            <th class="text-center">Desc.</th>
                            <th class="text-end">Subtotal</th>
                            <th class="text-end">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($orden->items as $i => $item)
                            <tr>
                                <td class="text-muted">{{ $i + 1 }}</td>
                                <td><span class="badge bg-light text-dark border">{{ $item->codigo ?? '-' }}</span></td>
                                <td>{{ $item->descripcion }}</td>
                                <td class="text-center">{{ \App\Helpers\Format::cantidad($item->cantidad) }}</td>
                                <td class="text-end">${{ number_format($item->precio_unitario, 0, ',', '.') }}</td>
                                <td class="text-center">{{ number_format($item->porcentaje_iva, 0) }}%</td>
                                <td class="text-center">
                                    @if($item->descuento_porcentaje > 0)
                                        <span class="text-danger">{{ number_format($item->descuento_porcentaje, 2) }}%</span>
                                        <div class="small text-muted">-${{ number_format($item->descuento_monto, 0, ',', '.') }}</div>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td class="text-end">${{ number_format($item->subtotal, 0, ',', '.') }}</td>
                                <td class="text-end fw-semibold">${{ number_format($item->total, 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    @php
                        $totalDescuentos = $orden->items->sum('descuento_monto');
                        $totalAntesDescuento = $orden->subtotal + $orden->monto_iva;
                    @endphp
                    <tfoot class="border-top">
                        <tr>
                            <td colspan="7"></td>
                            <td class="text-end text-muted small">Subtotal bruto</td>
                            <td class="text-end fw-medium">${{ number_format($orden->subtotal, 0, ',', '.') }}</td>
                        </tr>
                        <tr>
                            <td colspan="7"></td>
                            <td class="text-end text-muted small">IVA</td>
                            <td class="text-end fw-medium">${{ number_format($orden->monto_iva, 0, ',', '.') }}</td>
                        </tr>
                        <tr>
                            <td colspan="7"></td>
                            <td class="text-end fw-bold">TOTAL</td>
                            <td class="text-end fw-bold fs-6">${{ number_format($totalAntesDescuento, 0, ',', '.') }}</td>
                        </tr>
                        @if($totalDescuentos > 0)
                            <tr>
                                <td colspan="7"></td>
                                <td class="text-end text-danger small">Descuento</td>
                                <td class="text-end fw-medium text-danger">-${{ number_format($totalDescuentos, 0, ',', '.') }}</td>
                            </tr>
                        @endif
                        <tr>
                            <td colspan="7"></td>
                            <td class="text-end fw-bold">Total con retenciones</td>
                            <td class="text-end fw-bold fs-6 text-primary">${{ number_format($orden->total, 0, ',', '.') }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            {{-- Desglose por categoria --}}
            @if($resumenCategorias->count() > 1)
                <div class="mt-3 pt-2 border-top">
                    <small class="text-muted d-block mb-2">Desglose por Categoria</small>
                    <div class="d-flex gap-3 flex-wrap">
                        @php
                            $catLabels = ['servicio' => 'Servicios', 'material' => 'Materiales', 'producto_terminado' => 'Productos'];
                            $catColors = ['servicio' => 'primary', 'material' => 'info', 'producto_terminado' => 'warning'];
                        @endphp
                        @foreach($resumenCategorias as $cat => $totales)
                            <div class="border rounded px-3 py-2">
                                <span class="badge bg-{{ $catColors[$cat] ?? 'secondary' }} bg-opacity-10 text-{{ $catColors[$cat] ?? 'secondary' }} small">
                                    {{ $catLabels[$cat] ?? ucfirst($cat) }}
                                </span>
                                <span class="fw-semibold ms-1">${{ number_format($totales['total'], 0, ',', '.') }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        @else
            <p class="text-muted mb-0">No hay items registrados.</p>
        @endif
    </div>
</div>

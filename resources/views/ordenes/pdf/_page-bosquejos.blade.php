{{-- Seccion Bosquejos (flujo continuo; salta de pagina solo si no cabe) --}}
<div class="page-con-margen">
    <div class="section-title" style="margin-bottom: 12px;">BOSQUEJOS</div>

    @php $cols = $bosquejosCols; @endphp
    <table class="bosquejos-table">
        @foreach($bosquejosData->chunk($cols) as $row)
            <tr>
                @foreach($row as $bosquejo)
                    <td style="width: {{ intval(100 / $cols) }}%;">
                        @if($bosquejo->base64)
                            <img src="{{ $bosquejo->base64 }}" class="bosquejo-img">
                        @else
                            <div style="background: #f3f4f6; padding: 20px; border: 1px solid #e5e7eb; text-align: center; color: #9ca3af; font-size: 8px;">
                                Imagen no disponible
                            </div>
                        @endif
                        <div class="bosquejo-nombre">
                            @if(!empty($bosquejo->pieza_nombre))
                                {{ $bosquejo->pieza_nombre }} &middot; Cant: {{ \App\Helpers\Format::cantidad($bosquejo->pieza_cantidad) }}
                            @else
                                {{ $bosquejo->nombre ?? '' }}
                            @endif
                        </div>
                    </td>
                @endforeach
                {{-- Celdas vacias para completar la fila --}}
                @for($i = $row->count(); $i < $cols; $i++)
                    <td style="width: {{ intval(100 / $cols) }}%;"></td>
                @endfor
            </tr>
        @endforeach
    </table>
</div>

@php
    /** @var array $matriz */
    $calibres = $matriz['calibres'];
    $bandas   = $matriz['bandas'];
    $largos   = $matriz['largos'];
    $servicios = $matriz['servicios'];
    $numCols = 1 + count($calibres);

    $fmtMm = function ($mm) {
        $s = rtrim(rtrim(number_format((float) $mm, 2, '.', ''), '0'), '.');
        return $s === '' ? '0' : $s;
    };
    $fmtPrecio = function ($v) {
        if ($v === null || $v === '') return '-';
        return '$' . number_format((float) $v, 0, ',', '.');
    };
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 18px 20px; }
        * { font-family: DejaVu Sans, sans-serif; }
        body { color: #222; font-size: 8px; }
        .marca { font-size: 9px; color: #4A7C59; font-weight: bold; margin-bottom: 4px; }
        .servicio { page-break-after: always; }
        .servicio:last-child { page-break-after: auto; }
        table { border-collapse: collapse; width: 100%; margin-bottom: 2px; }
        td, th { border: 0.5px solid #cfcfcf; padding: 3px 2px; text-align: center; }
        .titulo td {
            background-color: #4A7C59; color: #fff; font-size: 12px;
            font-weight: bold; padding: 6px; text-align: center; letter-spacing: .5px;
        }
        thead th {
            background-color: #4A7C59; color: #fff; font-weight: bold; font-size: 7.5px;
        }
        thead th .mm { display: block; font-weight: normal; font-size: 6.5px; opacity: .9; }
        .banda td {
            background-color: #E91E63; color: #fff; font-weight: bold;
            text-align: left; padding-left: 8px; font-size: 8.5px;
        }
        .lbl { background-color: #f3f3f3; font-weight: bold; text-align: center; white-space: nowrap; }
        .precio { text-align: right; padding-right: 4px; }
        .minima td {
            border: none; padding-top: 5px; font-weight: bold; color: #C0392B; text-align: left;
        }
        .minima .sin-iva { color: #666; font-weight: normal; }
    </style>
</head>
<body>
    @foreach ($servicios as $servicio)
        <div class="servicio">
            <div class="marca">SINDEN S.A.S. &mdash; Tabla de valores de servicios (corte / doblez)</div>

            <table>
                <tr class="titulo">
                    <td colspan="{{ $numCols }}">{{ $servicio['etiqueta'] }}</td>
                </tr>
            </table>

            <table>
                <thead>
                    <tr>
                        <th>Largo</th>
                        @foreach ($calibres as $cal)
                            <th>{{ $cal['clave'] }}<span class="mm">({{ $fmtMm($cal['mm']) }}mm)</span></th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($bandas as $bIdx => $banda)
                        <tr class="banda">
                            <td colspan="{{ $numCols }}">{{ $banda['label'] }} Servicios</td>
                        </tr>
                        @foreach ($largos as $lIdx => $largo)
                            <tr>
                                <td class="lbl">{{ $largo['label'] }}</td>
                                @foreach ($calibres as $cIdx => $cal)
                                    <td class="precio">{{ $fmtPrecio($servicio['grid'][$bIdx][$lIdx][$cIdx] ?? null) }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>

            <table>
                <tr class="minima">
                    <td colspan="{{ $numCols }}">
                        MINIMA: {{ $fmtPrecio($servicio['precio_minimo']) }}
                        <span class="sin-iva">&nbsp;&nbsp;&bull;&nbsp;&nbsp;PRECIOS SIN IVA</span>
                    </td>
                </tr>
            </table>
        </div>
    @endforeach
</body>
</html>

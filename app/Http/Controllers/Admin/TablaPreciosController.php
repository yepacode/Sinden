<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ConfiguracionSistema;
use App\Models\TablaPrecioServicio;
use App\Traits\RegistraActividad;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Exports\TablaPreciosExport;
use App\Exports\TablaPreciosTemplate;
use App\Imports\TablaPreciosImport;

class TablaPreciosController extends Controller
{
    use RegistraActividad;

    /** 6 rangos de cantidad de servicios (sub-tablas del PDF). */
    private const CANTIDADES_SERVICIOS = [
        ['min' => 1,   'max' => 5],
        ['min' => 6,   'max' => 25],
        ['min' => 26,  'max' => 50],
        ['min' => 51,  'max' => 100],
        ['min' => 101, 'max' => 200],
        ['min' => 201, 'max' => null],
    ];

    /** 4 rangos de largo en mm (filas del PDF). */
    private const LARGOS_MM = [
        ['min' => 1,   'max' => 60],
        ['min' => 61,  'max' => 120],
        ['min' => 121, 'max' => 320],
        ['min' => 321, 'max' => null],
    ];

    /**
     * Vista principal + AJAX para cargar grid de precios.
     */
    public function index(Request $request)
    {
        if ($request->ajax() && $request->filled('tipo_servicio')) {
            return $this->cargarGrid($request);
        }

        $servicios = TablaPrecioServicio::getDistinctServicios();
        $totalServicios = $servicios->count();
        $totalRegistros = TablaPrecioServicio::count();
        $ultimaActualizacion = TablaPrecioServicio::max('updated_at');

        return view('admin.tabla-precios.index', compact(
            'servicios', 'totalServicios', 'totalRegistros', 'ultimaActualizacion'
        ));
    }

    /**
     * Carga grid de precios (AJAX).
     * Devuelve las 6 sub-tablas (cantidad de servicios) para el servicio dado.
     */
    private function cargarGrid(Request $request)
    {
        $tipoServicio = $request->tipo_servicio;

        $precios = TablaPrecioServicio::forServicio($tipoServicio)
            ->orderBy('cantidad_servicios_min')
            ->orderBy('largo_mm_min')
            ->orderBy('calibre_mm')
            ->get();

        $calibres = TablaPrecioServicio::getDistinctCalibres();
        $cantidadesServicios = TablaPrecioServicio::getDistinctCantidadesServicios();
        $largosMm = TablaPrecioServicio::getDistinctLargosMm();

        $servicio = $precios->first();

        return response()->json([
            'calibres' => $calibres,
            'cantidades_servicios' => $cantidadesServicios,
            'largos_mm' => $largosMm,
            'precios' => $precios,
            'servicio_etiqueta' => $servicio?->etiqueta_servicio ?? '',
            'precio_minimo' => $servicio?->precio_minimo ?? 0,
        ]);
    }

    /**
     * Actualizar precios masivamente (AJAX).
     */
    public function updatePrecios(Request $request)
    {
        $request->validate([
            'precios' => 'required|array|min:1',
            'precios.*.id' => 'required|integer|exists:tabla_precios_servicios,id',
            'precios.*.precio' => 'required|numeric|min:0',
        ]);

        $cambios = [];

        foreach ($request->precios as $item) {
            $registro = TablaPrecioServicio::find($item['id']);
            $precioAnterior = $registro->precio;
            $precioNuevo = $item['precio'];

            if ((float)$precioAnterior !== (float)$precioNuevo) {
                $registro->update(['precio' => $precioNuevo]);
                $cambios[] = [
                    'calibre' => $registro->clave_calibre,
                    'cantidad_servicios' => $registro->cantidad_servicios_min . '-' . ($registro->cantidad_servicios_max ?? '∞'),
                    'largo_mm' => $registro->largo_mm_min . '-' . ($registro->largo_mm_max ?? '∞'),
                    'anterior' => $precioAnterior,
                    'nuevo' => $precioNuevo,
                ];
            }
        }

        if (count($cambios) > 0) {
            $servicio = TablaPrecioServicio::find($request->precios[0]['id']);
            $cambiosFormateados = [];
            foreach ($cambios as $c) {
                $key = "{$c['calibre']} | servicios {$c['cantidad_servicios']} | largo {$c['largo_mm']}mm";
                $cambiosFormateados[$key] = [
                    'antes' => $c['anterior'],
                    'despues' => $c['nuevo'],
                ];
            }
            $this->registrarActividad(
                'tabla_precios.precios_actualizados',
                'Actualizados ' . count($cambios) . ' precios de ' . ($servicio->etiqueta_servicio ?? ''),
                null,
                [
                    'tipo_cambio' => 'update',
                    'modelo' => 'TablaPrecioServicio',
                    'modelo_id' => null,
                    'cambios' => $cambiosFormateados,
                ]
            );
        }

        return response()->json([
            'success' => true,
            'message' => count($cambios) . ' precio(s) actualizado(s).',
        ]);
    }

    /**
     * Lista de tipos de servicio (AJAX).
     */
    public function servicios()
    {
        $servicios = TablaPrecioServicio::getDistinctServicios();
        $servicios->each(function ($s) {
            $s->total_registros = TablaPrecioServicio::forServicio($s->tipo_servicio)->count();
        });

        return response()->json($servicios);
    }

    /**
     * Crear nuevo tipo de servicio con 312 registros (13 calibres x 6 cantidades x 4 largos).
     */
    public function storeServicio(Request $request)
    {
        $request->validate([
            'tipo_servicio' => 'required|string|max:100',
            'etiqueta_servicio' => 'required|string|max:255',
            'precio_minimo' => 'required|numeric|min:0',
        ]);

        $clave = Str::slug($request->tipo_servicio, '_');

        // Verificar unicidad
        if (TablaPrecioServicio::where('tipo_servicio', $clave)->exists()) {
            return response()->json(['message' => 'Ya existe un servicio con esa clave.'], 422);
        }

        $calibres = ConfiguracionSistema::get('calibres_disponibles', []);

        $records = [];
        $now = now();

        foreach ($calibres as $calibre) {
            foreach (self::CANTIDADES_SERVICIOS as $cantidad) {
                foreach (self::LARGOS_MM as $largo) {
                    $records[] = [
                        'tipo_servicio' => $clave,
                        'etiqueta_servicio' => $request->etiqueta_servicio,
                        'clave_calibre' => $calibre['calibre'],
                        'calibre_mm' => $calibre['mm'],
                        'cantidad_servicios_min' => $cantidad['min'],
                        'cantidad_servicios_max' => $cantidad['max'],
                        'largo_mm_min' => $largo['min'],
                        'largo_mm_max' => $largo['max'],
                        'precio' => $request->precio_minimo,
                        'precio_minimo' => $request->precio_minimo,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
        }

        foreach (array_chunk($records, 500) as $chunk) {
            TablaPrecioServicio::insert($chunk);
        }

        $this->registrarActividad(
            'tabla_precios.servicio_creado',
            'Creado tipo de servicio: ' . $request->etiqueta_servicio . ' (' . count($records) . ' registros)',
            null,
            [
                'tipo_cambio' => 'create',
                'modelo' => 'TablaPrecioServicio',
                'modelo_id' => null,
                'cambios' => [
                    'tipo_servicio' => ['antes' => null, 'despues' => $clave],
                    'etiqueta_servicio' => ['antes' => null, 'despues' => $request->etiqueta_servicio],
                    'precio_minimo' => ['antes' => null, 'despues' => $request->precio_minimo],
                ],
                'registros_creados' => count($records),
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Servicio creado con ' . count($records) . ' registros de precios.',
        ]);
    }

    /**
     * Actualizar tipo de servicio.
     */
    public function updateServicio(Request $request, string $tipo_servicio)
    {
        $request->validate([
            'etiqueta_servicio' => 'required|string|max:255',
            'precio_minimo' => 'required|numeric|min:0',
        ]);

        $servicioPrev = TablaPrecioServicio::forServicio($tipo_servicio)->first();

        $actualizados = TablaPrecioServicio::forServicio($tipo_servicio)->update([
            'etiqueta_servicio' => $request->etiqueta_servicio,
            'precio_minimo' => $request->precio_minimo,
        ]);

        if ($actualizados === 0) {
            return response()->json(['message' => 'Servicio no encontrado.'], 404);
        }

        $this->registrarActividad(
            'tabla_precios.servicio_actualizado',
            'Actualizado servicio: ' . $request->etiqueta_servicio,
            null,
            [
                'tipo_cambio' => 'update',
                'modelo' => 'TablaPrecioServicio',
                'modelo_id' => null,
                'cambios' => [
                    'etiqueta_servicio' => [
                        'antes' => $servicioPrev?->etiqueta_servicio,
                        'despues' => $request->etiqueta_servicio,
                    ],
                    'precio_minimo' => [
                        'antes' => $servicioPrev?->precio_minimo,
                        'despues' => $request->precio_minimo,
                    ],
                ],
                'registros_afectados' => $actualizados,
            ]
        );

        return response()->json(['success' => true, 'message' => 'Servicio actualizado.']);
    }

    /**
     * Eliminar tipo de servicio y todos sus registros.
     */
    public function destroyServicio(string $tipo_servicio)
    {
        $servicio = TablaPrecioServicio::forServicio($tipo_servicio)->first();

        if (!$servicio) {
            return response()->json(['message' => 'Servicio no encontrado.'], 404);
        }

        $etiqueta = $servicio->etiqueta_servicio;
        $precioMinimo = $servicio->precio_minimo;
        $eliminados = TablaPrecioServicio::forServicio($tipo_servicio)->delete();

        $this->registrarActividad(
            'tabla_precios.servicio_eliminado',
            'Eliminado servicio: ' . $etiqueta . ' (' . $eliminados . ' registros)',
            null,
            [
                'tipo_cambio' => 'delete',
                'modelo' => 'TablaPrecioServicio',
                'modelo_id' => null,
                'cambios' => [
                    'tipo_servicio' => ['antes' => $tipo_servicio, 'despues' => null],
                    'etiqueta_servicio' => ['antes' => $etiqueta, 'despues' => null],
                    'precio_minimo' => ['antes' => $precioMinimo, 'despues' => null],
                ],
                'registros_eliminados' => $eliminados,
            ]
        );

        return response()->json(['success' => true, 'message' => 'Servicio eliminado (' . $eliminados . ' registros).']);
    }

    /**
     * Exportar precios a Excel.
     */
    public function exportExcel(Request $request)
    {
        $tipoServicio = $request->tipo_servicio;
        $nombre = 'tabla-precios' . ($tipoServicio ? '-' . $tipoServicio : '') . '.xlsx';

        return Excel::download(new TablaPreciosExport($tipoServicio), $nombre);
    }

    /**
     * Exportar precios a PDF (mismo layout de las tablas del cliente).
     * Si se pasa tipo_servicio, exporta solo ese; si no, las 6 tablas.
     */
    public function exportPdf(Request $request)
    {
        // DomPDF con tablas grandes consume memoria; el hosting puede tener
        // memory_limit bajo (ej. 32M). Aseguramos memoria suficiente.
        ini_set('memory_limit', '512M');

        $tipoServicio = $request->tipo_servicio;
        $matriz = TablaPreciosExport::buildMatriz($tipoServicio);

        $pdf = Pdf::loadView('admin.tabla-precios.pdf', ['matriz' => $matriz])
            ->setPaper('letter', 'landscape');

        $nombre = 'tabla-precios' . ($tipoServicio ? '-' . $tipoServicio : '') . '.pdf';

        return $pdf->download($nombre);
    }

    /**
     * Importar precios desde Excel.
     */
    public function importExcel(Request $request)
    {
        $request->validate([
            'archivo' => 'required|file|mimes:xlsx,xls|max:5120',
        ], [
            'archivo.required' => 'Debe seleccionar un archivo Excel.',
            'archivo.file' => 'El archivo es invalido.',
            'archivo.mimes' => 'El archivo debe ser formato .xlsx o .xls.',
            'archivo.max' => 'El archivo no puede superar 5 MB. Tamano maximo permitido: 5 MB.',
            'archivo.uploaded' => 'El archivo supera el tamano maximo permitido (5 MB). Reduzca el tamano e intente de nuevo.',
        ]);

        $import = new TablaPreciosImport();
        $sheets = Excel::toArray($import, $request->file('archivo'));
        $import->procesar($sheets);

        $actualizados = $import->getActualizados();
        $sinCambio = $import->getSinCambio();
        $noEncontradas = $import->getNoEncontradas();
        $invalidas = $import->getInvalidas();
        $vacias = $import->getVacias();

        $this->registrarActividad(
            'tabla_precios.importacion',
            'Importacion de precios: ' . $actualizados . ' actualizados, ' . $sinCambio . ' sin cambio, ' . $noEncontradas . ' no encontradas, ' . $invalidas . ' invalidas',
            null,
            [
                'tipo_cambio' => 'update',
                'modelo' => 'TablaPrecioServicio',
                'modelo_id' => null,
                'cambios' => [],
                'registros_actualizados' => $actualizados,
                'registros_sin_cambio' => $sinCambio,
                'registros_no_encontrados' => $noEncontradas,
                'registros_invalidos' => $invalidas,
                'filas_vacias' => $vacias,
                'archivo' => $request->file('archivo')->getClientOriginalName(),
            ]
        );

        $partes = [$actualizados . ' actualizado(s)'];
        if ($sinCambio > 0) $partes[] = $sinCambio . ' sin cambio';
        if ($noEncontradas > 0) $partes[] = $noEncontradas . ' no encontrado(s)';
        if ($invalidas > 0) $partes[] = $invalidas . ' invalido(s)';
        if ($vacias > 0) $partes[] = $vacias . ' vacia(s)';

        return response()->json([
            'success' => true,
            'message' => implode(' | ', $partes),
            'detalles' => [
                'actualizados' => $actualizados,
                'sin_cambio' => $sinCambio,
                'no_encontradas' => $noEncontradas,
                'invalidas' => $invalidas,
                'vacias' => $vacias,
                'errores' => $import->getErrores(),
            ],
        ]);
    }

    /**
     * Descargar plantilla vacia para importacion de precios.
     */
    public function plantillaExcel()
    {
        return Excel::download(new TablaPreciosTemplate(), 'plantilla-tabla-precios.xlsx');
    }
}

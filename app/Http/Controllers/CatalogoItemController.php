<?php

namespace App\Http\Controllers;

use App\Models\CatalogoItem;
use App\Models\CatalogoItemImport;
use App\Models\ConfiguracionSistema;
use App\Exports\CatalogoItemsExport;
use App\Exports\CatalogoItemsTemplateExport;
use App\Imports\CatalogoItemsImport;
use App\Traits\RegistraActividad;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Yajra\DataTables\Facades\DataTables;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;

class CatalogoItemController extends Controller
{
    use RegistraActividad;

    protected const CATEGORIAS = [
        'servicio' => 'SERVICIO',
        'material' => 'MATERIAL',
        'producto_terminado' => 'PRODUCTO TERMINADO',
    ];

    protected const CATEGORIA_COLORS = [
        'servicio' => 'primary',
        'material' => 'info',
        'producto_terminado' => 'warning',
    ];

    public function index(Request $request)
    {
        $isContabilidad = $request->routeIs('contabilidad.*');
        $canEdit = auth()->user()->can('editar_catalogo_items');

        if ($request->ajax()) {
            $query = CatalogoItem::query();

            return DataTables::of($query)
                ->addColumn('categoria_label', function ($item) {
                    $label = self::CATEGORIAS[$item->categoria] ?? strtoupper($item->categoria);
                    $color = self::CATEGORIA_COLORS[$item->categoria] ?? 'secondary';
                    return '<span class="status-badge ' . $color . '">' . $label . '</span>';
                })
                ->addColumn('precio_formato', function ($item) {
                    return '$' . number_format($item->precio_unitario, 0, ',', '.');
                })
                ->addColumn('iva_formato', function ($item) {
                    return number_format($item->porcentaje_iva, 0) . '%';
                })
                ->addColumn('estado', function ($item) {
                    $variant = $item->activo ? 'success' : 'danger';
                    $text = $item->activo ? 'ACTIVO' : 'INACTIVO';
                    return '<span class="status-badge ' . $variant . '">' . $text . '</span>';
                })
                ->addColumn('acciones', function ($item) use ($isContabilidad, $canEdit) {
                    $prefix = $isContabilidad ? 'contabilidad' : 'recepcion';
                    $editUrl = $canEdit ? route($prefix . '.items.edit', $item) : '#';

                    $html = '<div class="action-buttons justify-content-end">';

                    if ($canEdit) {
                        $html .= '<a href="' . $editUrl . '" class="action-btn edit" title="Editar" data-tooltip="Editar"><i class="bi bi-pencil"></i></a>';

                        $toggleIcon = $item->activo ? 'toggle-on' : 'toggle-off';
                        $toggleTitle = $item->activo ? 'Desactivar' : 'Activar';
                        $html .= '<button type="button" class="action-btn" title="' . $toggleTitle . '" data-tooltip="' . $toggleTitle . '"'
                            . ' onclick="toggleActivo(' . $item->id . ', \'' . addslashes($item->codigo) . '\')">'
                            . '<i class="bi bi-' . $toggleIcon . '"></i></button>';
                    }

                    $html .= '</div>';
                    return $html;
                })
                ->editColumn('descripcion', function ($item) {
                    return mb_strlen($item->descripcion) > 60
                        ? mb_substr($item->descripcion, 0, 60) . '...'
                        : $item->descripcion;
                })
                ->editColumn('created_at', function ($item) {
                    return $item->created_at ? $item->created_at->format('d/m/Y') : '-';
                })
                ->rawColumns(['categoria_label', 'estado', 'acciones'])
                ->make(true);
        }

        $totalItems = CatalogoItem::count();
        $itemsActivos = CatalogoItem::where('activo', true)->count();
        $itemsServicios = CatalogoItem::where('activo', true)->where('categoria', 'servicio')->count();
        $itemsMateriales = CatalogoItem::where('activo', true)->where('categoria', 'material')->count();

        $routePrefix = $isContabilidad ? 'contabilidad' : 'recepcion';

        return view('catalogo-items.index', compact(
            'totalItems', 'itemsActivos', 'itemsServicios', 'itemsMateriales',
            'isContabilidad', 'canEdit', 'routePrefix'
        ));
    }

    public function create()
    {
        $categorias = self::CATEGORIAS;
        $ivaDefecto = (int) round((float) ConfiguracionSistema::get('porcentaje_iva_defecto', 19));
        return view('catalogo-items.create', compact('categorias', 'ivaDefecto'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate(
            $this->rules(),
            $this->messages()
        );

        $item = CatalogoItem::create($validated);

        $this->registrarCreacion(
            'catalogo_item.creado',
            "Se creo el item: {$item->codigo} - {$item->descripcion}",
            $item
        );

        return redirect()->route('recepcion.items.index')
            ->with('success', 'Item creado exitosamente.');
    }

    public function edit(CatalogoItem $item)
    {
        $categorias = self::CATEGORIAS;
        $routePrefix = request()->routeIs('contabilidad.*') ? 'contabilidad' : 'recepcion';
        return view('catalogo-items.edit', compact('item', 'categorias', 'routePrefix'));
    }

    public function update(Request $request, CatalogoItem $item)
    {
        $validated = $request->validate(
            $this->rules($item->id),
            $this->messages()
        );

        $valoresOriginales = $item->getOriginal();
        $item->update($validated);

        $routePrefix = $request->routeIs('contabilidad.*') ? 'contabilidad' : 'recepcion';

        $this->registrarActualizacion(
            'catalogo_item.actualizado',
            "Se actualizo el item: {$item->codigo}",
            $item,
            $valoresOriginales
        );

        return redirect()->route($routePrefix . '.items.index')
            ->with('success', 'Item actualizado exitosamente.');
    }

    public function toggleActivo(CatalogoItem $item)
    {
        $valoresOriginales = $item->getOriginal();
        $item->activo = !$item->activo;
        $item->save();

        $accion = $item->activo ? 'activo' : 'desactivo';

        $this->registrarActualizacion(
            'catalogo_item.actualizado',
            "Se {$accion} el item: {$item->codigo}",
            $item,
            $valoresOriginales
        );

        if (request()->ajax()) {
            return response()->json([
                'success' => true,
                'activo' => $item->activo,
                'message' => "Item {$accion} exitosamente.",
            ]);
        }

        return redirect()->back()->with('success', "Item {$accion} exitosamente.");
    }

    public function autocomplete(Request $request)
    {
        $q = trim($request->get('q', ''));

        $query = CatalogoItem::where('activo', true)
            ->select('id', 'codigo', 'descripcion', 'precio_unitario', 'porcentaje_iva', 'categoria');

        if ($q !== '') {
            // Busqueda por PALABRAS: parte lo escrito en palabras y trae items que tengan
            // AL MENOS UNA (en codigo o descripcion), en cualquier orden; ordena primero
            // los que coinciden con MAS palabras (relevancia). Antes buscaba la frase
            // completa pegada, por eso con 2+ palabras no encontraba nada.
            $palabras = array_values(array_filter(
                preg_split('/\s+/', $q),
                fn($w) => $w !== ''
            ));

            // Filtro: el item coincide si CUALQUIER palabra esta en codigo o descripcion.
            $query->where(function ($qb) use ($palabras) {
                foreach ($palabras as $w) {
                    $qb->orWhere('codigo', 'LIKE', "%{$w}%")
                       ->orWhere('descripcion', 'LIKE', "%{$w}%");
                }
            });

            // Relevancia: cuantas palabras coinciden (mas coincidencias => mas arriba).
            $casos = [];
            $binds = [];
            foreach ($palabras as $w) {
                $casos[] = '(CASE WHEN codigo LIKE ? OR descripcion LIKE ? THEN 1 ELSE 0 END)';
                $binds[] = "%{$w}%";
                $binds[] = "%{$w}%";
            }
            $query->orderByRaw(implode(' + ', $casos) . ' DESC', $binds);
        }

        $items = $query
            ->orderBy('codigo')
            ->limit(20)
            ->get();

        return response()->json($items);
    }

    public function exportExcel()
    {
        $items = CatalogoItem::orderBy('codigo')->get();
        return Excel::download(new CatalogoItemsExport($items), 'catalogo-items-' . now()->format('Y-m-d') . '.xlsx');
    }

    public function exportPdf()
    {
        ini_set('memory_limit', '512M');

        $items = CatalogoItem::where('activo', true)->orderBy('codigo')->get();
        $fecha = now()->timezone('America/Bogota')->format('d/m/Y H:i');

        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
            body { font-family: sans-serif; font-size: 11px; color: #1f2937; }
            h1 { color: #4A7C59; font-size: 18px; margin-bottom: 2px; }
            .fecha { color: #6b7280; font-size: 10px; margin-bottom: 15px; }
            table { width: 100%; border-collapse: collapse; }
            th { background: #4A7C59; color: white; padding: 8px 6px; text-align: left; font-size: 10px; }
            td { padding: 6px; border-bottom: 1px solid #e5e7eb; }
            tr:nth-child(even) td { background: #f9fafb; }
            .text-right { text-align: right; }
            .footer { margin-top: 20px; font-size: 9px; color: #9ca3af; text-align: center; }
        </style></head><body>';
        $html .= '<h1>Catalogo de Items Activos</h1>';
        $html .= '<p class="fecha">Generado: ' . $fecha . '</p>';
        $html .= '<table><thead><tr>';
        $html .= '<th>Codigo</th><th>Descripcion</th><th>Categoria</th><th class="text-right">Precio Unit.</th><th class="text-right">IVA %</th>';
        $html .= '</tr></thead><tbody>';

        foreach ($items as $item) {
            $catLabel = self::CATEGORIAS[$item->categoria] ?? strtoupper($item->categoria);
            $html .= '<tr>';
            $html .= '<td>' . e($item->codigo) . '</td>';
            $html .= '<td>' . e($item->descripcion) . '</td>';
            $html .= '<td>' . $catLabel . '</td>';
            $html .= '<td class="text-right">$' . number_format($item->precio_unitario, 0, ',', '.') . '</td>';
            $html .= '<td class="text-right">' . number_format($item->porcentaje_iva, 0) . '%</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';
        $html .= '<div class="footer">SINDEN S.A.S. - ' . now()->year . '</div>';
        $html .= '</body></html>';

        return Pdf::loadHTML($html)
            ->setPaper('letter', 'landscape')
            ->download('catalogo-items-' . now()->format('Y-m-d') . '.pdf');
    }

    public function downloadTemplate()
    {
        return Excel::download(new CatalogoItemsTemplateExport(), 'plantilla-catalogo-items.xlsx');
    }

    public function importExcel(Request $request)
    {
        $request->validate([
            'archivo' => 'required|file|mimes:xlsx,xls|max:5120',
        ], [
            'archivo.required' => 'Debe seleccionar un archivo Excel.',
            'archivo.mimes' => 'El archivo debe ser formato .xlsx o .xls.',
            'archivo.max' => 'El archivo no puede superar 5 MB. Tamano maximo permitido: 5 MB.',
            'archivo.uploaded' => 'El archivo supera el tamano maximo permitido (5 MB). Reduzca el tamano e intente de nuevo.',
        ]);

        $archivo = $request->file('archivo');

        $importRecord = CatalogoItemImport::create([
            'usuario_id' => Auth::id(),
            'nombre_archivo' => $archivo->getClientOriginalName(),
            'estado' => 'procesando',
        ]);

        try {
            $import = new CatalogoItemsImport();
            Excel::import($import, $archivo);

            $importRecord->update([
                'total_filas' => $import->getTotalFilas(),
                'creados' => $import->getCreados(),
                'actualizados' => $import->getActualizados(),
                'errores' => $import->getErrores(),
                'estado' => $import->getErrores() > 0
                    ? ($import->getCreados() + $import->getActualizados() > 0 ? 'completado_con_errores' : 'fallido')
                    : 'completado',
                'detalle_log' => $import->getDetalleLog(),
            ]);

            $this->registrarActividad(
                'catalogo_item.importacion',
                "Importacion de items: {$import->getCreados()} creados, {$import->getActualizados()} actualizados, {$import->getErrores()} errores",
                null,
                [
                    'tipo_cambio' => 'import',
                    'modelo' => 'CatalogoItem',
                    'archivo' => $archivo->getClientOriginalName(),
                    'creados' => $import->getCreados(),
                    'actualizados' => $import->getActualizados(),
                    'errores' => $import->getErrores(),
                ]
            );

            return response()->json([
                'success' => true,
                'message' => "Importacion completada: {$import->getCreados()} creados, {$import->getActualizados()} actualizados, {$import->getErrores()} errores.",
                'data' => [
                    'id' => $importRecord->id,
                    'creados' => $import->getCreados(),
                    'actualizados' => $import->getActualizados(),
                    'errores' => $import->getErrores(),
                    'total' => $import->getTotalFilas(),
                ],
            ]);
        } catch (\Exception $e) {
            $importRecord->update([
                'estado' => 'fallido',
                'detalle_log' => [['fila' => 0, 'codigo' => '-', 'accion' => 'error', 'mensaje' => $e->getMessage(), 'datos' => []]],
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al procesar el archivo: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function importHistory()
    {
        $imports = CatalogoItemImport::with('usuario:id,name')
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get()
            ->map(function ($import) {
                return [
                    'id' => $import->id,
                    'usuario' => $import->usuario->name ?? '-',
                    'nombre_archivo' => $import->nombre_archivo,
                    'total_filas' => $import->total_filas,
                    'creados' => $import->creados,
                    'actualizados' => $import->actualizados,
                    'errores' => $import->errores,
                    'estado' => $import->estado,
                    'fecha' => $import->created_at->format('d/m/Y H:i'),
                ];
            });

        return response()->json($imports);
    }

    public function importDetail(CatalogoItemImport $import)
    {
        return response()->json([
            'id' => $import->id,
            'nombre_archivo' => $import->nombre_archivo,
            'detalle_log' => $import->detalle_log ?? [],
        ]);
    }

    protected function rules(?int $itemId = null): array
    {
        $uniqueRule = 'unique:catalogo_items,codigo';
        if ($itemId) {
            $uniqueRule .= ',' . $itemId;
        }

        return [
            'codigo' => 'required|string|max:50|' . $uniqueRule,
            'descripcion' => 'required|string',
            'precio_unitario' => 'required|numeric|min:0',
            'porcentaje_iva' => 'required|numeric|min:0|max:100',
            'categoria' => 'required|in:servicio,material,producto_terminado',
        ];
    }

    protected function messages(): array
    {
        return [
            'codigo.required' => 'El codigo del item es obligatorio.',
            'codigo.max' => 'El codigo no puede exceder 50 caracteres.',
            'codigo.unique' => 'Ya existe un item con este codigo.',
            'descripcion.required' => 'La descripcion es obligatoria.',
            'precio_unitario.required' => 'El precio unitario es obligatorio.',
            'precio_unitario.numeric' => 'El precio unitario debe ser un numero.',
            'precio_unitario.min' => 'El precio unitario no puede ser negativo.',
            'porcentaje_iva.required' => 'El porcentaje de IVA es obligatorio.',
            'porcentaje_iva.numeric' => 'El porcentaje de IVA debe ser un numero.',
            'porcentaje_iva.min' => 'El porcentaje de IVA no puede ser negativo.',
            'porcentaje_iva.max' => 'El porcentaje de IVA no puede exceder 100.',
            'categoria.required' => 'La categoria es obligatoria.',
            'categoria.in' => 'La categoria seleccionada no es valida.',
        ];
    }
}

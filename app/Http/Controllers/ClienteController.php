<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\ClienteImport;
use App\Models\ConfiguracionSistema;
use App\Exports\ClientesExport;
use App\Exports\ClientesTemplateExport;
use App\Imports\ClientesImport;
use App\Traits\RegistraActividad;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Yajra\DataTables\Facades\DataTables;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;

class ClienteController extends Controller
{
    use RegistraActividad;

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $query = Cliente::query();
            $predeterminadoId = (int) ConfiguracionSistema::get('cliente_predeterminado_id', 0);

            return DataTables::of($query)
                ->addColumn('estado', function ($cliente) {
                    $variant = $cliente->activo ? 'success' : 'danger';
                    $text = $cliente->activo ? 'ACTIVO' : 'INACTIVO';
                    return '<span class="status-badge ' . $variant . '">' . $text . '</span>';
                })
                ->addColumn('acciones', function ($cliente) use ($predeterminadoId) {
                    $viewUrl = route('recepcion.clientes.show', $cliente);
                    $editUrl = route('recepcion.clientes.edit', $cliente);
                    $esMostrador = $predeterminadoId && $cliente->id === $predeterminadoId;

                    $html = '<div class="action-buttons justify-content-end">'
                        . '<a href="' . $viewUrl . '" class="action-btn view" title="Ver" data-tooltip="Ver"><i class="bi bi-eye"></i></a>'
                        . '<a href="' . $editUrl . '" class="action-btn edit" title="Editar" data-tooltip="Editar"><i class="bi bi-pencil"></i></a>';

                    if (!$esMostrador) {
                        $toggleIcon = $cliente->activo ? 'toggle-on' : 'toggle-off';
                        $toggleTitle = $cliente->activo ? 'Desactivar' : 'Activar';
                        $html .= '<button type="button" class="action-btn" title="' . $toggleTitle . '" data-tooltip="' . $toggleTitle . '"'
                            . ' onclick="toggleActivo(' . $cliente->id . ', \'' . addslashes($cliente->nombre) . '\')">'
                            . '<i class="bi bi-' . $toggleIcon . '"></i></button>';
                    }

                    $html .= '</div>';
                    return $html;
                })
                ->editColumn('created_at', function ($cliente) {
                    return $cliente->created_at ? $cliente->created_at->format('d/m/Y') : '-';
                })
                ->editColumn('cedula', function ($cliente) {
                    return $cliente->cedula ?? '-';
                })
                ->editColumn('correo', function ($cliente) {
                    return $cliente->correo ?? '-';
                })
                ->editColumn('celular_1', function ($cliente) {
                    return $cliente->celular_1 ?? '-';
                })
                ->rawColumns(['estado', 'acciones'])
                ->make(true);
        }

        $totalClientes = Cliente::count();
        $clientesActivos = Cliente::where('activo', true)->count();
        $clientesInactivos = Cliente::where('activo', false)->count();
        $clientesRecientes = Cliente::where('created_at', '>=', now()->subDays(30))->count();

        return view('clientes.index', compact(
            'totalClientes', 'clientesActivos', 'clientesInactivos', 'clientesRecientes'
        ));
    }

    public function create()
    {
        return view('clientes.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate(
            $this->rules(),
            $this->messages()
        );

        $cliente = Cliente::create($validated);

        $this->registrarCreacion(
            'cliente.creado',
            "Se creo el cliente: {$cliente->nombre}",
            $cliente
        );

        return redirect()->route('recepcion.clientes.index')
            ->with('success', 'Cliente creado exitosamente.');
    }

    public function show(Cliente $cliente)
    {
        $cliente->load(['ordenes' => function ($q) {
            $q->latest();
        }]);

        return view('clientes.show', compact('cliente'));
    }

    public function edit(Cliente $cliente)
    {
        return view('clientes.edit', compact('cliente'));
    }

    public function update(Request $request, Cliente $cliente)
    {
        $validated = $request->validate(
            $this->rules(),
            $this->messages()
        );

        $valoresOriginales = $cliente->getOriginal();
        $cliente->update($validated);

        $this->registrarActualizacion(
            'cliente.actualizado',
            "Se actualizo el cliente: {$cliente->nombre}",
            $cliente,
            $valoresOriginales
        );

        return redirect()->route('recepcion.clientes.index')
            ->with('success', 'Cliente actualizado exitosamente.');
    }

    public function toggleActivo(Cliente $cliente)
    {
        $predeterminadoId = ConfiguracionSistema::get('cliente_predeterminado_id');
        if ($predeterminadoId && (int) $predeterminadoId === $cliente->id) {
            if (request()->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'El cliente mostrador no se puede desactivar.',
                ], 422);
            }
            return redirect()->back()->with('error', 'El cliente mostrador no se puede desactivar.');
        }

        $valoresOriginales = $cliente->getOriginal();
        $cliente->activo = !$cliente->activo;
        $cliente->save();

        $accion = $cliente->activo ? 'activo' : 'desactivo';

        $this->registrarActualizacion(
            'cliente.actualizado',
            "Se {$accion} el cliente: {$cliente->nombre}",
            $cliente,
            $valoresOriginales
        );

        if (request()->ajax()) {
            return response()->json([
                'success' => true,
                'activo' => $cliente->activo,
                'message' => "Cliente {$accion} exitosamente.",
            ]);
        }

        return redirect()->back()->with('success', "Cliente {$accion} exitosamente.");
    }

    public function autocomplete(Request $request)
    {
        $q = $request->get('q', '');

        if (strlen($q) < 2) {
            return response()->json([]);
        }

        $clientes = Cliente::where('activo', true)
            ->where(function ($query) use ($q) {
                $query->where('nombre', 'LIKE', "%{$q}%")
                    ->orWhere('cedula', 'LIKE', "%{$q}%")
                    ->orWhere('celular_1', 'LIKE', "%{$q}%")
                    ->orWhere('celular_2', 'LIKE', "%{$q}%")
                    ->orWhere('correo', 'LIKE', "%{$q}%");
            })
            ->select('id', 'nombre', 'cedula', 'celular_1', 'correo')
            ->limit(10)
            ->get();

        return response()->json($clientes);
    }

    public function exportExcel()
    {
        $clientes = Cliente::orderBy('nombre')->get();
        return Excel::download(new ClientesExport($clientes), 'clientes-' . now()->format('Y-m-d') . '.xlsx');
    }

    public function exportPdf()
    {
        ini_set('memory_limit', '512M');

        $clientes = Cliente::where('activo', true)->orderBy('nombre')->get();
        $fecha = now()->timezone('America/Bogota')->format('d/m/Y H:i');

        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
            body { font-family: sans-serif; font-size: 11px; color: #1f2937; }
            h1 { color: #4A7C59; font-size: 18px; margin-bottom: 2px; }
            .fecha { color: #6b7280; font-size: 10px; margin-bottom: 15px; }
            table { width: 100%; border-collapse: collapse; }
            th { background: #4A7C59; color: white; padding: 8px 6px; text-align: left; font-size: 10px; }
            td { padding: 6px; border-bottom: 1px solid #e5e7eb; }
            tr:nth-child(even) td { background: #f9fafb; }
            .footer { margin-top: 20px; font-size: 9px; color: #9ca3af; text-align: center; }
        </style></head><body>';
        $html .= '<h1>Listado de Clientes Activos</h1>';
        $html .= '<p class="fecha">Generado: ' . $fecha . '</p>';
        $html .= '<table><thead><tr>';
        $html .= '<th>ID</th><th>Nombre</th><th>Cedula</th><th>Correo</th><th>Celular</th><th>Direccion</th>';
        $html .= '</tr></thead><tbody>';

        foreach ($clientes as $c) {
            $html .= '<tr>';
            $html .= '<td>' . $c->id . '</td>';
            $html .= '<td>' . e($c->nombre) . '</td>';
            $html .= '<td>' . e($c->cedula ?? '-') . '</td>';
            $html .= '<td>' . e($c->correo ?? '-') . '</td>';
            $html .= '<td>' . e($c->celular_1 ?? '-') . '</td>';
            $html .= '<td>' . e($c->direccion ?? '-') . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';
        $html .= '<div class="footer">SINDEN S.A.S. - ' . now()->year . '</div>';
        $html .= '</body></html>';

        return Pdf::loadHTML($html)
            ->setPaper('letter', 'landscape')
            ->download('clientes-' . now()->format('Y-m-d') . '.pdf');
    }

    // =========================================================
    // Importacion masiva por Excel
    // =========================================================

    public function downloadTemplate()
    {
        return Excel::download(new ClientesTemplateExport(), 'plantilla-clientes.xlsx');
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

        $importRecord = ClienteImport::create([
            'usuario_id' => Auth::id(),
            'nombre_archivo' => $archivo->getClientOriginalName(),
            'estado' => 'procesando',
        ]);

        try {
            $import = new ClientesImport();
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
                'cliente.importacion',
                "Importacion de clientes: {$import->getCreados()} creados, {$import->getActualizados()} actualizados, {$import->getErrores()} errores",
                null,
                [
                    'tipo_cambio' => 'import',
                    'modelo' => 'Cliente',
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
        $imports = ClienteImport::with('usuario:id,name')
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

    public function importDetail(ClienteImport $import)
    {
        return response()->json([
            'id' => $import->id,
            'nombre_archivo' => $import->nombre_archivo,
            'detalle_log' => $import->detalle_log ?? [],
        ]);
    }

    protected function rules(): array
    {
        return [
            'nombre' => 'required|string|max:255',
            'cedula' => 'nullable|string|max:20',
            'direccion' => 'nullable|string',
            'correo' => 'nullable|email|max:255',
            'celular_1' => 'nullable|string|max:20',
            'celular_2' => 'nullable|string|max:20',
        ];
    }

    protected function messages(): array
    {
        return [
            'nombre.required' => 'El nombre del cliente es obligatorio.',
            'nombre.max' => 'El nombre no puede exceder 255 caracteres.',
            'cedula.max' => 'La cedula no puede exceder 20 caracteres.',
            'correo.email' => 'El correo electronico debe ser un email valido.',
            'correo.max' => 'El correo no puede exceder 255 caracteres.',
            'celular_1.max' => 'El celular no puede exceder 20 caracteres.',
            'celular_2.max' => 'El celular secundario no puede exceder 20 caracteres.',
        ];
    }
}

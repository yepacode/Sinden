<?php

namespace App\Http\Controllers;

use App\Models\Orden;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use ZipArchive;

class OrdenPdfController extends Controller
{
    /**
     * GET /recepcion/ordenes/{orden}/pdf
     * Genera PDF individual de una orden.
     */
    public function show(Request $request, Orden $orden)
    {
        // El PDF embebe imagenes (logo, bosquejos, firma) en base64 y DomPDF
        // consume bastante memoria; en hosting con memory_limit bajo (ej. 32M)
        // esto causa error 500. Aseguramos memoria suficiente en tiempo de ejecucion.
        ini_set('memory_limit', '512M');

        $cols = $this->validateCols($request);
        $data = $this->prepareOrdenData($orden, $cols);

        $pdf = Pdf::loadView('ordenes.pdf.orden', $data)
            ->setPaper('letter', 'portrait');

        $filename = $orden->estado_trabajo === 'borrador'
            ? 'Cotizacion-Borrador-' . $orden->id . '.pdf'
            : 'Orden-' . ($orden->numero_orden ?? 'Borrador-' . $orden->id) . '.pdf';

        if ($request->input('download', 1)) {
            return $pdf->download($filename);
        }

        return $pdf->stream($filename);
    }

    /**
     * GET /recepcion/ordenes/pdf-multiple
     * Genera un solo PDF con multiples ordenes separadas por page-break.
     */
    public function multiple(Request $request)
    {
        ini_set('memory_limit', '512M');

        $ids = array_filter(explode(',', $request->input('ids', '')));
        if (empty($ids)) {
            return back()->with('error', 'Seleccione al menos una orden.');
        }

        $cols = $this->validateCols($request);
        $ordenesData = [];

        foreach ($ids as $id) {
            $orden = Orden::findOrFail($id);
            $ordenesData[] = $this->prepareOrdenData($orden, $cols);
        }

        $pdf = Pdf::loadView('ordenes.pdf.orden-multiple', [
            'ordenesData' => $ordenesData,
        ])->setPaper('letter', 'portrait');

        return $pdf->download('Ordenes-' . now()->format('Y-m-d') . '.pdf');
    }

    /**
     * GET /recepcion/ordenes/pdf-zip
     * Genera PDFs individuales empaquetados en ZIP.
     */
    public function zip(Request $request)
    {
        ini_set('memory_limit', '512M');

        $ids = array_filter(explode(',', $request->input('ids', '')));
        if (empty($ids)) {
            return back()->with('error', 'Seleccione al menos una orden.');
        }

        $cols = $this->validateCols($request);
        $tempFile = tempnam(sys_get_temp_dir(), 'ordenes_pdf_');
        $zip = new ZipArchive();
        $zip->open($tempFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($ids as $id) {
            $orden = Orden::findOrFail($id);
            $data = $this->prepareOrdenData($orden, $cols);
            $pdfContent = Pdf::loadView('ordenes.pdf.orden', $data)
                ->setPaper('letter', 'portrait')
                ->output();
            $filename = 'Orden-' . ($orden->numero_orden ?? 'Borrador-' . $orden->id) . '.pdf';
            $zip->addFromString($filename, $pdfContent);
        }

        $zip->close();

        return response()->download($tempFile, 'Ordenes-' . now()->format('Y-m-d') . '.zip')
            ->deleteFileAfterSend(true);
    }

    /**
     * Valida y retorna el numero de columnas para bosquejos.
     */
    private function validateCols(Request $request): int
    {
        $cols = (int) $request->input('bosquejos_cols', 2);
        return in_array($cols, [1, 2, 3, 4]) ? $cols : 2;
    }

    /**
     * Prepara todos los datos necesarios para renderizar el PDF de una orden.
     */
    private function prepareOrdenData(Orden $orden, int $bosquejosCols): array
    {
        $orden->load([
            'cliente',
            'creador',
            'items',
            'bosquejos',
            'piezas.bosquejo',
            'piezas.operarioActual',
            'pagos' => function ($q) {
                $q->withTrashed()->with(['registradoPorUsuario', 'aprobadoPorUsuario']);
            },
            'entregas.piezas.ordenPieza',
            'entregas.entregadaPorUsuario',
        ]);

        // Logo empresa
        $logoBase64 = $this->imageToBase64('images/SINDEN_logo_transparente.png');

        // Firma cliente
        $firmaBase64 = '';
        if ($orden->ruta_firma_cliente) {
            $firmaBase64 = $this->imageToBase64($orden->ruta_firma_cliente);
        }

        // Bosquejos con base64
        $useThumbnail = $bosquejosCols >= 3;
        // Mapa bosquejo_id -> pieza, para identificar cada bosquejo en el PDF con el
        // nombre de su pieza y la cantidad (lo pidio el cliente: que se sepa a que
        // corresponde cada dibujo, no solo "Dibujo N").
        $piezaPorBosquejo = $orden->piezas->keyBy('orden_bosquejo_id');
        $bosquejosData = $orden->bosquejos->sortBy('orden_visual')->map(function ($b) use ($useThumbnail, $piezaPorBosquejo) {
            $ruta = $useThumbnail && $b->ruta_miniatura ? $b->ruta_miniatura : $b->ruta_archivo;
            $pieza = $piezaPorBosquejo->get($b->id);
            return (object) [
                'nombre' => $b->nombre,
                'pieza_nombre' => $pieza ? $pieza->nombre : null,
                'pieza_cantidad' => $pieza ? $pieza->cantidad : null,
                'base64' => $this->imageToBase64($ruta),
            ];
        });

        // Resumen por categorias
        $resumenCategorias = $orden->items->groupBy('categoria')->map(function ($items) {
            return [
                'subtotal' => $items->sum('subtotal'),
                'iva' => $items->sum('monto_iva'),
                'total' => $items->sum('total'),
            ];
        });

        // Pagos aprobados (no eliminados)
        $pagosAprobados = $orden->pagos->filter(function ($p) {
            return $p->aprobado && !$p->trashed();
        });

        return [
            'orden' => $orden,
            'logoBase64' => $logoBase64,
            'firmaBase64' => $firmaBase64,
            'bosquejosData' => $bosquejosData,
            'bosquejosCols' => $bosquejosCols,
            'resumenCategorias' => $resumenCategorias,
            'pagosAprobados' => $pagosAprobados,
            'generadoPor' => auth()->user()->name,
            'fechaGeneracion' => now()->timezone('America/Bogota')->format('d/m/Y H:i'),
        ];
    }

    /**
     * Convierte una imagen (ruta relativa a public/) a data URI base64.
     */
    private function imageToBase64(string $relativePath): string
    {
        $absPath = public_path($relativePath);
        if (!file_exists($absPath)) {
            return '';
        }

        $ext = strtolower(pathinfo($absPath, PATHINFO_EXTENSION));
        $mimeMap = [
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
        ];
        $mime = $mimeMap[$ext] ?? 'image/png';

        return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($absPath));
    }
}

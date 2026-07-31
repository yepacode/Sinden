<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

/**
 * Una hoja = un servicio, reproduciendo el layout de las tablas del cliente
 * (PDF "Tabla valores servicios Cortes cizalla.pdf"):
 *
 *   Fila 1: TITULO DEL SERVICIO
 *   Fila 2: (vacio) | #22 | #20 | ... | 1/2"                 (calibres)
 *   Fila 3: Largo   | (0.76mm) | (0.91mm) | ... | (12.7mm)   (espesor en mm)
 *   Fila 4: "1-5 Servicios"                                   (sub-tabla / banda)
 *   Fila 5: 1-60    | $.. | $.. | ...
 *   Fila 6: 61-120  | ...
 *   Fila 7: 121-320 | ...
 *   Fila 8: >320    | ...
 *   ... (6 bandas x [1 encabezado + 4 filas de largo])
 *   Fila 34: MINIMA: | $.. | PRECIOS SIN IVA
 *
 * Este mismo layout es el que el importador sabe leer, de modo que el archivo
 * exportado (o el que envie el cliente en este formato) hace round-trip.
 */
class ServicioPreciosSheet implements FromArray, WithStyles, WithTitle, WithColumnWidths
{
    private const VERDE = 'FF4A7C59';
    private const ROSA  = 'FFE91E63';
    private const GRIS  = 'FFF1F1F1';

    private string $etiqueta;
    private string $titulo;
    private $precioMinimo;
    /** @var array<int,array{clave:string,mm:float}> */
    private array $calibres;
    /** @var array<int,array{min:int,max:?int,label:string}> */
    private array $bandas;
    /** @var array<int,array{min:int,max:?int,label:string}> */
    private array $largos;
    /** @var array precios[bandIdx][largoIdx][calIdx] */
    private array $grid;

    // Indices calculados del layout (fijos por conteos).
    private int $numCols;
    private int $filaMinima;

    public function __construct(
        string $etiqueta,
        string $titulo,
        $precioMinimo,
        array $calibres,
        array $bandas,
        array $largos,
        array $grid
    ) {
        $this->etiqueta     = $etiqueta;
        $this->titulo       = $titulo;
        $this->precioMinimo = $precioMinimo;
        $this->calibres     = $calibres;
        $this->bandas       = $bandas;
        $this->largos       = $largos;
        $this->grid         = $grid;

        $this->numCols    = 1 + count($calibres);
        $filasPorBanda    = 1 + count($largos);
        $this->filaMinima = 4 + count($bandas) * $filasPorBanda;
    }

    public function title(): string
    {
        return $this->titulo;
    }

    public function columnWidths(): array
    {
        $widths = ['A' => 16];
        foreach ($this->calibres as $i => $cal) {
            $widths[$this->col($i + 1)] = 11;
        }
        return $widths;
    }

    public function array(): array
    {
        $rows = [];

        // Fila 1: titulo del servicio.
        $rows[] = [$this->etiqueta];

        // Fila 2: calibres.
        $filaCal = [''];
        foreach ($this->calibres as $cal) {
            $filaCal[] = $cal['clave'];
        }
        $rows[] = $filaCal;

        // Fila 3: espesor mm.
        $filaMm = ['Largo'];
        foreach ($this->calibres as $cal) {
            $filaMm[] = '(' . $this->fmtMm($cal['mm']) . 'mm)';
        }
        $rows[] = $filaMm;

        // Bandas (sub-tablas por cantidad de servicios).
        foreach ($this->bandas as $bIdx => $banda) {
            $rows[] = [$banda['label'] . ' Servicios'];
            foreach ($this->largos as $lIdx => $largo) {
                $fila = [$largo['label']];
                foreach ($this->calibres as $cIdx => $cal) {
                    $fila[] = $this->grid[$bIdx][$lIdx][$cIdx] ?? null;
                }
                $rows[] = $fila;
            }
        }

        // Fila final: minima.
        $filaMin = ['MINIMA:', $this->precioMinimo, 'PRECIOS SIN IVA'];
        $rows[] = $filaMin;

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        $ultCol = $this->col($this->numCols - 1);
        $formatoMoneda = '"$"#,##0';

        // Fila 1: titulo (merge + verde).
        $sheet->mergeCells("A1:{$ultCol}1");
        $sheet->getStyle("A1:{$ultCol}1")->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::VERDE]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(24);

        // Filas 2 y 3: encabezados de calibre y mm (verde claro).
        $sheet->getStyle("A2:{$ultCol}3")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::VERDE]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // Bandas y filas de largo.
        $filasPorBanda = 1 + count($this->largos);
        foreach ($this->bandas as $bIdx => $banda) {
            $filaBanda = 4 + $bIdx * $filasPorBanda;

            // Encabezado de banda (rosa).
            $sheet->mergeCells("A{$filaBanda}:{$ultCol}{$filaBanda}");
            $sheet->getStyle("A{$filaBanda}:{$ultCol}{$filaBanda}")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::ROSA]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);

            // Filas de largo: label en negrita + precios con formato moneda.
            $primeraFila = $filaBanda + 1;
            $ultimaFila  = $filaBanda + count($this->largos);
            $sheet->getStyle("A{$primeraFila}:A{$ultimaFila}")->applyFromArray([
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::GRIS]],
            ]);
            $sheet->getStyle("B{$primeraFila}:{$ultCol}{$ultimaFila}")
                ->getNumberFormat()->setFormatCode($formatoMoneda);
        }

        // Fila minima.
        $fm = $this->filaMinima;
        $sheet->getStyle("A{$fm}:B{$fm}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFC0392B']],
        ]);
        $sheet->getStyle("B{$fm}")->getNumberFormat()->setFormatCode($formatoMoneda);

        // Bordes en toda la tabla.
        $sheet->getStyle("A1:{$ultCol}{$fm}")->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFDDDDDD']]],
        ]);

        return [];
    }

    /** Numero de columna (0-based) -> letra Excel (0=A). */
    private function col(int $idx): string
    {
        return \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($idx + 1);
    }

    /** Formatea el espesor en mm quitando ceros sobrantes: 1.90->1.9, 4.00->4, 12.70->12.7. */
    private function fmtMm($mm): string
    {
        $s = rtrim(rtrim(number_format((float) $mm, 2, '.', ''), '0'), '.');
        return $s === '' ? '0' : $s;
    }
}

<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class HistorialFinancieroExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    protected $ordenes;

    public function __construct($ordenes)
    {
        $this->ordenes = $ordenes;
    }

    public function collection()
    {
        return $this->ordenes;
    }

    public function headings(): array
    {
        return [
            'Orden',
            'Cliente',
            'Fecha',
            'Subtotal',
            'IVA',
            'Total',
            'Pagado',
            'Saldo',
            '% Pagado',
            'Estado Pago',
            'Pagos',
        ];
    }

    public function map($orden): array
    {
        if ($orden->saldo <= 0 && $orden->total_pagado > 0) {
            $estadoPago = 'PAGADA';
        } elseif ($orden->total_pagado > 0) {
            $estadoPago = 'SALDO PEND.';
        } else {
            $estadoPago = 'SIN PAGOS';
        }

        $pct = $orden->total > 0 ? round(($orden->total_pagado / $orden->total) * 100) : 0;

        return [
            $orden->numero_orden ?? '-',
            $orden->cliente->nombre ?? '-',
            $orden->created_at->format('d/m/Y'),
            '$' . number_format($orden->subtotal, 0, '.', ','),
            '$' . number_format($orden->monto_iva, 0, '.', ','),
            '$' . number_format($orden->total, 0, '.', ','),
            '$' . number_format($orden->total_pagado, 0, '.', ','),
            '$' . number_format($orden->saldo, 0, '.', ','),
            $pct . '%',
            $estadoPago,
            $orden->pagos->count(),
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FF4A7C59'],
                ],
            ],
        ];
    }
}

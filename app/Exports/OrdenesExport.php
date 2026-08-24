<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class OrdenesExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
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
            'ID',
            'Orden #',
            'Cliente',
            'Fecha Creacion',
            'Fecha Entrega',
            'Estado Trabajo',
            'Estado Entrega',
            'Estado Pago',
            'Descuento',
            'Subtotal',
            'IVA',
            'Total',
            'Pagado',
            'Saldo',
        ];
    }

    public function map($orden): array
    {
        return [
            $orden->id,
            $orden->numero_orden ?? 'Borrador',
            $orden->cliente->nombre ?? '-',
            $orden->created_at ? $orden->created_at->format('d/m/Y') : '-',
            $orden->fecha_entrega ? $orden->fecha_entrega->format('d/m/Y') : '-',
            strtoupper(str_replace('_', ' ', $orden->estado_trabajo)),
            $orden->estado_entrega ? strtoupper(str_replace('_', ' ', $orden->estado_entrega)) : '-',
            $orden->estado_pago ? strtoupper(str_replace('_', ' ', $orden->estado_pago)) : '-',
            number_format($orden->items->sum('descuento_monto'), 0, '.', ','),
            number_format($orden->subtotal, 0, '.', ','),
            number_format($orden->monto_iva, 0, '.', ','),
            number_format($orden->total, 0, '.', ','),
            number_format($orden->total_pagado, 0, '.', ','),
            number_format($orden->saldo, 0, '.', ','),
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

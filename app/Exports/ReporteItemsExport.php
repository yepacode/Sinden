<?php

namespace App\Exports;

use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ReporteItemsExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    protected $items;

    protected const CATEGORIAS = [
        'servicio' => 'SERVICIO',
        'material' => 'MATERIAL',
        'producto_terminado' => 'PRODUCTO TERMINADO',
    ];

    public function __construct($items)
    {
        $this->items = $items;
    }

    public function collection()
    {
        return $this->items;
    }

    public function headings(): array
    {
        return [
            'Orden',
            'Fecha Orden',
            'Codigo',
            'Descripcion',
            'Categoria',
            'Cantidad',
            'Precio Unitario',
            'Descuento %',
            'Descuento $',
            'Subtotal',
            'IVA',
            'Total',
        ];
    }

    public function map($item): array
    {
        return [
            $item->numero_orden,
            Carbon::parse($item->fecha_orden)->format('d/m/Y'),
            $item->codigo,
            $item->descripcion,
            self::CATEGORIAS[$item->categoria] ?? strtoupper($item->categoria),
            number_format($item->cantidad, 2),
            '$' . number_format($item->precio_unitario, 0, ',', '.'),
            $item->descuento_porcentaje > 0 ? \App\Helpers\Format::cantidad($item->descuento_porcentaje) . '%' : '-',
            '$' . number_format($item->descuento_monto, 0, ',', '.'),
            '$' . number_format($item->subtotal, 0, ',', '.'),
            '$' . number_format($item->monto_iva, 0, ',', '.'),
            '$' . number_format($item->total, 0, ',', '.'),
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

<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CatalogoItemsExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
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
            'ID',
            'Codigo',
            'Descripcion',
            'Categoria',
            'Precio Unitario',
            '% IVA',
            'Estado',
            'Fecha Registro',
        ];
    }

    public function map($item): array
    {
        return [
            $item->id,
            $item->codigo,
            $item->descripcion,
            self::CATEGORIAS[$item->categoria] ?? strtoupper($item->categoria),
            '$' . number_format($item->precio_unitario, 0, '.', ','),
            number_format($item->porcentaje_iva, 0) . '%',
            $item->activo ? 'Activo' : 'Inactivo',
            $item->created_at ? $item->created_at->format('d/m/Y') : '-',
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

<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ClientesDatosSheet implements FromArray, WithHeadings, WithStyles, ShouldAutoSize, WithTitle
{
    public function title(): string
    {
        return 'Datos';
    }

    public function headings(): array
    {
        return [
            'nombre',
            'cedula',
            'correo',
            'celular_1',
            'celular_2',
            'direccion',
        ];
    }

    public function array(): array
    {
        return [
            ['Juan Perez Ejemplo', '1098765432', 'juan@ejemplo.com', '3001234567', '', 'Calle 10 # 5-20'],
            ['Distribuidora Ejemplo SAS', '900123456-7', 'ventas@ejemplo.com', '3109876543', '6076543210', 'Cra 27 # 34-10'],
            ['Cliente Sin Cedula Ejemplo', '', '', '3151112233', '', ''],
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FF4A7C59'],
                ],
            ],
            // Filas de ejemplo en gris cursiva
            2 => ['font' => ['italic' => true, 'color' => ['argb' => 'FF999999']]],
            3 => ['font' => ['italic' => true, 'color' => ['argb' => 'FF999999']]],
            4 => ['font' => ['italic' => true, 'color' => ['argb' => 'FF999999']]],
        ];
    }
}

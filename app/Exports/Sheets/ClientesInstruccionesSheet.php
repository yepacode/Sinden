<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ClientesInstruccionesSheet implements FromArray, WithStyles, WithTitle, WithColumnWidths
{
    public function title(): string
    {
        return 'Instrucciones';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 25,
            'B' => 65,
        ];
    }

    public function array(): array
    {
        return [
            ['INSTRUCCIONES DE IMPORTACION', ''],
            ['', ''],
            ['Columna', 'Descripcion'],
            ['nombre', 'Nombre del cliente o razon social (obligatorio, max 255 caracteres).'],
            ['cedula', 'Cedula o NIT del cliente (opcional, max 20 caracteres). Ver reglas abajo.'],
            ['correo', 'Correo electronico (opcional). Debe ser un email valido. Ej: cliente@correo.com'],
            ['celular_1', 'Celular principal / WhatsApp (opcional, max 20 caracteres).'],
            ['celular_2', 'Celular secundario (opcional, max 20 caracteres).'],
            ['direccion', 'Direccion del cliente (opcional).'],
            ['', ''],
            ['REGLAS IMPORTANTES', ''],
            ['Con cedula/NIT', 'Si la cedula/NIT ya existe, se ACTUALIZAN los datos de ese cliente. Si no existe, se crea uno nuevo.'],
            ['Sin cedula/NIT', 'Si la fila NO trae cedula/NIT, SIEMPRE se crea un cliente nuevo (no se puede identificar duplicados).'],
            ['Filas con error', 'Las filas con errores se omiten sin afectar las demas. Revise el reporte al finalizar.'],
            ['Clientes nuevos', 'Los clientes creados quedan con estado ACTIVO.'],
            ['Filas de ejemplo', 'Las filas de ejemplo en la hoja "Datos" estan en gris cursiva. Eliminelas antes de importar o reemplacelas con sus datos.'],
            ['Solo obligatorio', 'El unico campo obligatorio es el nombre. Los demas pueden ir vacios.'],
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'size' => 14, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FF4A7C59'],
                ],
            ],
            3 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FF4A7C59'],
                ],
            ],
            11 => [
                'font' => ['bold' => true, 'size' => 12, 'color' => ['argb' => 'FF4A7C59']],
            ],
        ];
    }
}

<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class OperarioOrdenesAsignadasExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    protected $ordenes;
    protected int $userId;

    public function __construct($ordenes, int $userId)
    {
        $this->ordenes = $ordenes;
        $this->userId = $userId;
    }

    public function collection()
    {
        return $this->ordenes;
    }

    public function headings(): array
    {
        return [
            'Orden #',
            'Cliente',
            'Fecha Entrega',
            'Hora Entrega',
            'Mis Piezas Pendientes',
            'Total Piezas Orden',
            'Estado Trabajo',
        ];
    }

    public function map($orden): array
    {
        $totalPiezas = $orden->piezas->count();
        $miasPendientes = $orden->piezas
            ->where('operario_actual_id', $this->userId)
            ->where('porcentaje_avance', '<', 100)
            ->count();

        return [
            $orden->numero_orden ?? '-',
            $orden->cliente->nombre ?? '-',
            $orden->fecha_entrega ? $orden->fecha_entrega->format('d/m/Y') : '-',
            $orden->hora_entrega_fmt ?? '-',
            $miasPendientes,
            $totalPiezas,
            strtoupper(str_replace('_', ' ', $orden->estado_trabajo)),
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

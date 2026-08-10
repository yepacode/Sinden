<?php

namespace App\Exports;

use App\Models\TipoPago;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PagosPendientesExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    protected $pagos;
    protected array $mapaBadges;

    public function __construct($pagos)
    {
        $this->pagos = $pagos;
        $this->mapaBadges = TipoPago::mapaBadges();
    }

    public function collection()
    {
        return $this->pagos;
    }

    public function headings(): array
    {
        return [
            'Fecha Registro',
            'Orden #',
            'Cliente',
            'Monto',
            'Metodo de Pago',
            'Referencia',
            'Registrado Por',
        ];
    }

    public function map($p): array
    {
        $cfg = $this->mapaBadges[$p->metodo_pago] ?? null;
        $metodo = $cfg['etiqueta'] ?? ($cfg['nombre'] ?? ucfirst($p->metodo_pago));

        return [
            $p->created_at ? $p->created_at->format('d/m/Y H:i') : '-',
            $p->orden->numero_orden ?? '-',
            $p->orden->cliente->nombre ?? '-',
            number_format($p->monto, 0, '.', ','),
            $metodo,
            $p->referencia_pago ?? '-',
            $p->registradoPorUsuario->name ?? '-',
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

<?php

namespace App\Exports;

use App\Exports\Sheets\ClientesDatosSheet;
use App\Exports\Sheets\ClientesInstruccionesSheet;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ClientesTemplateExport implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            new ClientesDatosSheet(),
            new ClientesInstruccionesSheet(),
        ];
    }
}

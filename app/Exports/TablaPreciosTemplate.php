<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Plantilla de importacion en el formato del cliente (layout matricial del PDF,
 * una hoja por servicio) con los precios actuales precargados. El usuario/cliente
 * edita los valores y vuelve a subir el archivo; el importador lee este mismo layout.
 */
class TablaPreciosTemplate implements WithMultipleSheets
{
    public function sheets(): array
    {
        return TablaPreciosExport::buildSheets(null);
    }
}

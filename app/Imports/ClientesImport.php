<?php

namespace App\Imports;

use App\Models\Cliente;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;

class ClientesImport implements ToCollection, WithHeadingRow, SkipsEmptyRows
{
    protected int $creados = 0;
    protected int $actualizados = 0;
    protected int $errores = 0;
    protected array $detalleLog = [];

    public function collection(Collection $rows)
    {
        foreach ($rows as $index => $row) {
            $fila = $index + 2; // La fila 1 es el encabezado

            try {
                // Saltar filas completamente vacias
                $valores = array_values(array_filter($row->toArray(), fn($v) => $v !== null && trim((string) $v) !== ''));
                if (empty($valores)) {
                    continue;
                }

                $nombre    = trim($row['nombre'] ?? '');
                $cedula    = trim($row['cedula'] ?? '');
                $correo    = trim($row['correo'] ?? '');
                $celular1  = trim($row['celular_1'] ?? '');
                $celular2  = trim($row['celular_2'] ?? '');
                $direccion = trim($row['direccion'] ?? '');

                // Etiqueta para el log (cedula si existe, si no el nombre)
                $etiqueta = $cedula !== '' ? $cedula : ($nombre !== '' ? $nombre : '(vacio)');

                // Validar
                $validator = Validator::make([
                    'nombre'    => $nombre,
                    'cedula'    => $cedula ?: null,
                    'correo'    => $correo ?: null,
                    'celular_1' => $celular1 ?: null,
                    'celular_2' => $celular2 ?: null,
                    'direccion' => $direccion ?: null,
                ], [
                    'nombre'    => 'required|string|max:255',
                    'cedula'    => 'nullable|string|max:20',
                    'correo'    => 'nullable|email|max:255',
                    'celular_1' => 'nullable|string|max:20',
                    'celular_2' => 'nullable|string|max:20',
                    'direccion' => 'nullable|string',
                ], [
                    'nombre.required' => 'El nombre es obligatorio.',
                    'nombre.max'      => 'El nombre no puede exceder 255 caracteres.',
                    'cedula.max'      => 'La cedula/NIT no puede exceder 20 caracteres.',
                    'correo.email'    => 'El correo debe ser un email valido.',
                    'correo.max'      => 'El correo no puede exceder 255 caracteres.',
                    'celular_1.max'   => 'El celular no puede exceder 20 caracteres.',
                    'celular_2.max'   => 'El celular secundario no puede exceder 20 caracteres.',
                ]);

                if ($validator->fails()) {
                    $this->errores++;
                    $this->detalleLog[] = [
                        'fila'    => $fila,
                        'codigo'  => $etiqueta,
                        'accion'  => 'error',
                        'mensaje' => implode(' ', $validator->errors()->all()),
                        'datos'   => $row->toArray(),
                    ];
                    continue;
                }

                $datos = [
                    'nombre'    => $nombre,
                    'cedula'    => $cedula ?: null,
                    'correo'    => $correo ?: null,
                    'celular_1' => $celular1 ?: null,
                    'celular_2' => $celular2 ?: null,
                    'direccion' => $direccion ?: null,
                ];

                // Upsert por cedula/NIT SOLO si viene con valor.
                // Sin cedula no podemos identificar de forma unica -> siempre se crea.
                $existente = $cedula !== ''
                    ? Cliente::where('cedula', $cedula)->first()
                    : null;

                if ($existente) {
                    $existente->update($datos);
                    $this->actualizados++;
                    $this->detalleLog[] = [
                        'fila'    => $fila,
                        'codigo'  => $etiqueta,
                        'accion'  => 'actualizado',
                        'mensaje' => 'Cliente actualizado exitosamente (coincidio la cedula/NIT).',
                        'datos'   => $datos,
                    ];
                } else {
                    Cliente::create(array_merge($datos, ['activo' => true]));
                    $this->creados++;
                    $this->detalleLog[] = [
                        'fila'    => $fila,
                        'codigo'  => $etiqueta,
                        'accion'  => 'creado',
                        'mensaje' => 'Cliente creado exitosamente.',
                        'datos'   => $datos,
                    ];
                }
            } catch (\Exception $e) {
                $this->errores++;
                $this->detalleLog[] = [
                    'fila'    => $fila,
                    'codigo'  => trim($row['cedula'] ?? '') ?: trim($row['nombre'] ?? '(vacio)'),
                    'accion'  => 'error',
                    'mensaje' => 'Error inesperado: ' . $e->getMessage(),
                    'datos'   => $row->toArray(),
                ];
            }
        }
    }

    public function getCreados(): int { return $this->creados; }
    public function getActualizados(): int { return $this->actualizados; }
    public function getErrores(): int { return $this->errores; }
    public function getDetalleLog(): array { return $this->detalleLog; }
    public function getTotalFilas(): int { return $this->creados + $this->actualizados + $this->errores; }
}

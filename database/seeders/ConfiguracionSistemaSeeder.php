<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ConfiguracionSistemaSeeder extends Seeder
{
    public function run()
    {
        $configuraciones = [
            [
                'clave' => 'nombre_empresa',
                'valor' => 'SINDEN S.A.S.',
                'tipo' => 'texto',
                'descripcion' => 'Nombre de la empresa',
            ],
            [
                'clave' => 'logo_empresa',
                'valor' => null,
                'tipo' => 'texto',
                'descripcion' => 'Ruta del logo de la empresa',
            ],
            [
                'clave' => 'direccion_empresa',
                'valor' => '',
                'tipo' => 'texto',
                'descripcion' => 'Direccion de la empresa',
            ],
            [
                'clave' => 'telefono_empresa',
                'valor' => '',
                'tipo' => 'texto',
                'descripcion' => 'Telefono de la empresa',
            ],
            [
                'clave' => 'nit_empresa',
                'valor' => '',
                'tipo' => 'texto',
                'descripcion' => 'NIT de la empresa',
            ],
            [
                'clave' => 'porcentaje_iva_defecto',
                'valor' => '19.00',
                'tipo' => 'decimal',
                'descripcion' => 'Porcentaje de IVA por defecto',
            ],
            [
                'clave' => 'timeout_inactividad_operario',
                'valor' => '10',
                'tipo' => 'entero',
                'descripcion' => 'Minutos de inactividad para cerrar sesion del operario',
            ],
            [
                'clave' => 'timeout_autoguardado_recepcion',
                'valor' => '5',
                'tipo' => 'entero',
                'descripcion' => 'Minutos para auto-guardado de recepcion',
            ],
            [
                'clave' => 'timeout_forzar_cierre',
                'valor' => '15',
                'tipo' => 'entero',
                'descripcion' => 'Segundos para forzar cierre de orden',
            ],
            [
                'clave' => 'dias_expiracion_borradores',
                'valor' => '30',
                'tipo' => 'entero',
                'descripcion' => 'Dias antes de eliminar borradores',
            ],
            [
                'clave' => 'dias_borradores_recientes',
                'valor' => '7',
                'tipo' => 'entero',
                'descripcion' => 'Dias para mostrar borradores recientes',
            ],
            [
                'clave' => 'usuario_notificar_baja_porcentaje',
                'valor' => null,
                'tipo' => 'entero',
                'descripcion' => 'ID de usuario a notificar cuando un operario baja el porcentaje',
            ],
            [
                'clave' => 'materiales_disponibles',
                'valor' => '["HR","CR","INOX","Galvanizado","Aluminio Liso","Alfajor","Alfajor HR","Acero 430"]',
                'tipo' => 'json',
                'descripcion' => 'Materiales disponibles para piezas',
            ],
            [
                'clave' => 'cliente_predeterminado_id',
                'valor' => null,
                'tipo' => 'entero',
                'descripcion' => 'ID del cliente predeterminado (mostrador)',
            ],
            [
                'clave' => 'imagen_fondo_login',
                'valor' => null,
                'tipo' => 'texto',
                'descripcion' => 'Imagen de fondo para login y pagina de inicio',
            ],
            [
                'clave' => 'color_texto_bienvenida',
                'valor' => '#1f2937',
                'tipo' => 'texto',
                'descripcion' => 'Color del texto de la pagina de bienvenida (titulo y subtitulo)',
            ],
            [
                'clave' => 'calibres_disponibles',
                'valor' => json_encode([
                    ['calibre' => '#22', 'mm' => 0.76],
                    ['calibre' => '#20', 'mm' => 0.91],
                    ['calibre' => '#18', 'mm' => 1.21],
                    ['calibre' => '#16', 'mm' => 1.52],
                    ['calibre' => '#14', 'mm' => 1.90],
                    ['calibre' => '#12', 'mm' => 2.66],
                    ['calibre' => '1/8"', 'mm' => 3.18],
                    ['calibre' => '4mm', 'mm' => 4.00],
                    ['calibre' => '3/16"', 'mm' => 4.76],
                    ['calibre' => '1/4"', 'mm' => 6.35],
                    ['calibre' => '5/16"', 'mm' => 7.94],
                    ['calibre' => '3/8"', 'mm' => 9.53],
                    ['calibre' => '1/2"', 'mm' => 12.70],
                ]),
                'tipo' => 'json',
                'descripcion' => 'Calibres disponibles con espesor en mm',
            ],
        ];

        $now = now();

        foreach ($configuraciones as $config) {
            DB::table('configuracion_sistema')->insert(array_merge($config, [
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }
    }
}

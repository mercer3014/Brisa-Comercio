<?php

namespace Database\Seeders;

use App\Models\Configuracion;
use Illuminate\Database\Seeder;

class ConfiguracionSeeder extends Seeder
{
    public function run(): void
    {
        $parametros = [
            ['clave' => 'max_intentos_login',      'valor' => '5',  'tipo_dato' => 'entero',  'descripcion' => 'Intentos fallidos permitidos antes de bloquear el acceso.'],
            ['clave' => 'ventana_bloqueo_minutos', 'valor' => '15', 'tipo_dato' => 'entero',  'descripcion' => 'Ventana (minutos) en la que se cuentan los intentos fallidos.'],
            ['clave' => 'sesion_minutos_expira',   'valor' => '30', 'tipo_dato' => 'entero',  'descripcion' => 'Minutos de inactividad antes de expirar la sesion.'],
            ['clave' => 'organizacion_por_defecto','valor' => '1',  'tipo_dato' => 'entero',  'descripcion' => 'Id de la organizacion mostrada por defecto (INE).'],
            ['clave' => 'lote_etl_filas',          'valor' => '1000','tipo_dato' => 'entero', 'descripcion' => 'Tamanio de lote para el procesamiento ETL.'],
        ];

        foreach ($parametros as $p) {
            Configuracion::updateOrCreate(
                ['clave' => $p['clave']],
                [
                    'valor' => $p['valor'],
                    'tipo_dato' => $p['tipo_dato'],
                    'descripcion' => $p['descripcion'],
                ]
            );
        }
    }
}

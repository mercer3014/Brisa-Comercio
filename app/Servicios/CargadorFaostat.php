<?php

namespace App\Servicios;

use App\Models\CargaArchivo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Carga archivos FAOSTAT hacia serie_indicador_agricola (organizacion_id=4).
 *
 * ESTADO: Loader listo y funcional. Los archivos Excel de FAOSTAT aún no
 * están disponibles (los datos provienen de documentos Word convertidos).
 * Cuando se tengan los Excel, colocarlos en storage/app/private/excels_fuente/
 * y cargarlos con tipo_flujo = 'FAOSTAT'.
 *
 * El loader detecta el subtipo por las cabeceras:
 * - FAOSTAT_POBLACION:       columnas sobre población rural/urbana
 * - FAOSTAT_FERTILIZANTES:   columnas sobre N/P/K
 * - FAOSTAT_SUBALIMENTACION: columnas sobre prevalencia
 * - FAOSTAT_CEREALES:        columnas sobre producción de cereales
 */
class CargadorFaostat
{
    private const ORG_ID = 4;

    public function __construct(private LectorArchivo $lector)
    {
    }

    public function cargar(CargaArchivo $carga): void
    {
        $proceso = $carga->procesos()->create([
            'estado'       => 'EN_EJECUCION',
            'fecha_inicio' => now(),
        ]);

        try {
            $ruta = $this->resolverRuta($carga->carga_id);
            $ext  = pathinfo($ruta, PATHINFO_EXTENSION);
            $carga->update(['estado' => 'PROCESANDO']);

            $fuenteId  = $this->resolverFuente();
            $archivoId = $this->crearArchivoFuente($carga, $ruta);
            $paisId    = $this->resolverPaisBolivia();

            // Idempotencia
            DB::table('serie_indicador_agricola')->where('archivo_id', $archivoId)->delete();

            // Leer cabeceras para detectar subtipo
            $lectura = $this->lector->leerCabecerasYMuestra($ruta, $ext, 2);
            $subtipo = $this->detectarSubtipo($lectura['cabeceras']);

            $lote    = [];
            $leidas  = 0;
            $validas = 0;
            $errores = 0;

            foreach ($this->lector->iterarAsociativo($ruta, $ext) as $fila) {
                $leidas++;
                try {
                    $filas = $this->mapearFila($fila, $archivoId, $fuenteId, $paisId, $subtipo, $carga);
                    foreach ($filas as $row) {
                        $lote[] = $row;
                        $validas++;
                        if (count($lote) >= 500) {
                            DB::table('serie_indicador_agricola')->insert($lote);
                            $lote = [];
                        }
                    }
                } catch (Throwable $e) {
                    $errores++;
                }
            }

            if (! empty($lote)) {
                DB::table('serie_indicador_agricola')->insert($lote);
            }

            $carga->update([
                'total_filas_leidas'  => $leidas,
                'total_filas_validas' => $validas,
                'total_filas_error'   => $errores,
                'estado'              => 'COMPLETADO',
            ]);

            $proceso->update([
                'estado'           => 'EXITOSO',
                'fecha_fin'        => now(),
                'filas_procesadas' => $leidas,
                'mensaje_log'      => "FAOSTAT ({$subtipo}): {$validas} series insertadas.",
            ]);
        } catch (Throwable $e) {
            $carga->update(['estado' => 'FALLIDO']);
            $proceso->update([
                'estado'      => 'FALLIDO',
                'fecha_fin'   => now(),
                'mensaje_log' => 'Error: '.$e->getMessage(),
            ]);
            report($e);
        }
    }

    // -------------------------------------------------------------------------

    /**
     * Convierte una fila del XLSX en 1 o más registros de serie_indicador_agricola.
     * Cada columna de medida genera un registro separado (modelo FAOSTAT largo).
     */
    private function mapearFila(
        array $fila, int $archivoId, int $fuenteId, ?int $paisId,
        string $subtipo, CargaArchivo $carga
    ): array {
        $anio = $this->buscarCol($fila, ['Año', 'Ano', 'Year', 'Período', 'Periodo', 'Period']);
        $gestion = (int) $this->num($anio);

        if ($gestion < 1950 || $gestion > 2100) {
            // Intentar extraer año de período tipo "2002-2004"
            if (preg_match('/(\d{4})/', (string) ($anio ?? ''), $m)) {
                $gestion = (int) $m[1];
            } else {
                return [];
            }
        }

        $tiempoId = $this->resolverTiempo($gestion);

        $medidas = $this->columnasMedida($subtipo);
        $filas   = [];

        foreach ($medidas as $colNombre => $elementoDesc) {
            $valor = $this->buscarCol($fila, [$colNombre]);
            if ($valor === null || $valor === '') {
                continue;
            }

            $elementoId = $this->resolverElemento($elementoDesc, $subtipo);

            $filas[] = [
                'organizacion_id'            => self::ORG_ID,
                'archivo_id'                 => $archivoId,
                'pais_id'                    => $paisId,
                'producto_codigo_externo_id' => null,
                'tiempo_id'                  => $tiempoId,
                'gestion'                    => $gestion,
                'elemento_id'                => $elementoId,
                'simbolo_id'                 => null,
                'valor'                      => $this->numNull($valor),
                'unidad'                     => $this->unidadElemento($subtipo, $elementoDesc),
                'atributos_extra'            => null,
            ];
        }

        return $filas;
    }

    private function columnasMedida(string $subtipo): array
    {
        return match ($subtipo) {
            'FAOSTAT_POBLACION' => [
                'Población rural'   => 'Población rural',
                'Población urbana'  => 'Población urbana',
                'Población total'   => 'Población total',
            ],
            'FAOSTAT_FERTILIZANTES' => [
                'Nitrógeno'  => 'Fertilizantes N',
                'Fósforo'    => 'Fertilizantes P',
                'Potasio'    => 'Fertilizantes K',
                'Total'      => 'Fertilizantes Total',
            ],
            'FAOSTAT_SUBALIMENTACION' => [
                'Prevalencia (%)'     => 'Prevalencia subalimentación',
                'Número (millones)'   => 'Personas subalimentadas',
            ],
            'FAOSTAT_CEREALES' => [
                'Producción (toneladas)' => 'Producción cereales',
                'Área cosechada (ha)'    => 'Área cosechada cereales',
                'Rendimiento (kg/ha)'    => 'Rendimiento cereales',
            ],
            default => [],
        };
    }

    private function unidadElemento(string $subtipo, string $desc): ?string
    {
        return match (true) {
            str_contains($desc, 'rural') || str_contains($desc, 'urbana') || str_contains($desc, 'total') => 'personas',
            str_contains($desc, 'Fertilizantes') => 'toneladas',
            str_contains($desc, 'Prevalencia') => '%',
            str_contains($desc, 'subalimentadas') => 'millones de personas',
            str_contains($desc, 'Producción') => 'toneladas',
            str_contains($desc, 'Área') => 'ha',
            str_contains($desc, 'Rendimiento') => 'kg/ha',
            default => null,
        };
    }

    private function detectarSubtipo(array $cabeceras): string
    {
        $norm = implode(' ', array_map('strtolower', $cabeceras));
        if (str_contains($norm, 'rural') || str_contains($norm, 'urbana')) {
            return 'FAOSTAT_POBLACION';
        }
        if (str_contains($norm, 'nitrógeno') || str_contains($norm, 'nitrogeno') || str_contains($norm, 'fertilizante')) {
            return 'FAOSTAT_FERTILIZANTES';
        }
        if (str_contains($norm, 'subalimentación') || str_contains($norm, 'subalimentacion') || str_contains($norm, 'prevalencia')) {
            return 'FAOSTAT_SUBALIMENTACION';
        }
        if (str_contains($norm, 'cereal') || str_contains($norm, 'producción') || str_contains($norm, 'rendimiento')) {
            return 'FAOSTAT_CEREALES';
        }
        return 'FAOSTAT_GENERICO';
    }

    // -------------------------------------------------------------------------

    private function resolverFuente(): int
    {
        $id = DB::table('fuente_datos')
            ->where('organizacion_id', self::ORG_ID)
            ->where('version_nomenclatura', 'FAOSTAT-base')
            ->value('fuente_id');

        return $id ? (int) $id : (int) DB::table('fuente_datos')->insertGetId([
            'organizacion_id'      => self::ORG_ID,
            'version_nomenclatura' => 'FAOSTAT-base',
            'fecha_descarga'       => now()->toDateString(),
        ], 'fuente_id');
    }

    private function resolverPaisBolivia(): ?int
    {
        return DB::table('pais')
            ->where(fn ($q) => $q->where('iso_alpha3', 'BOL')->orWhere('iso_alpha2', 'BO'))
            ->value('pais_id');
    }

    private function resolverTiempo(int $gestion): int
    {
        $id = DB::table('tiempo')->where('gestion', $gestion)->where('mes', 0)->value('tiempo_id');
        if (! $id) {
            $id = (int) DB::table('tiempo')->insertGetId([
                'gestion'          => $gestion,
                'mes'              => 0,
                'nombre_mes'       => 'Anual',
                'trimestre'        => 0,
                'semestre'         => 0,
                'fecha_inicio_mes' => \Carbon\Carbon::createFromDate($gestion, 1, 1)->toDateString(),
            ], 'tiempo_id');
        }
        return (int) $id;
    }

    private function resolverElemento(string $descripcion, string $subtipo): int
    {
        $id = DB::table('faostat_elemento')
            ->where('nombre_elemento', $descripcion)
            ->value('elemento_id');

        return $id ? (int) $id : (int) DB::table('faostat_elemento')->insertGetId([
            'codigo_elemento' => substr(md5($descripcion), 0, 8),
            'nombre_elemento' => $descripcion,
            'tipo_comercio'   => $subtipo,
        ], 'elemento_id');
    }

    private function crearArchivoFuente(CargaArchivo $carga, string $ruta): int
    {
        return (int) DB::table('archivo_fuente')->insertGetId([
            'organizacion_id' => self::ORG_ID,
            'ruta_archivo'    => $ruta,
            
            'fecha_carga'     => now(),
        ], 'archivo_id');
    }

    private function resolverRuta(int $cargaId): string
    {
        foreach (['xlsx', 'xlsm', 'csv'] as $ext) {
            $rel = "cargas/{$cargaId}/datos.{$ext}";
            if (Storage::disk('local')->exists($rel)) {
                return Storage::disk('local')->path($rel);
            }
        }
        throw new \RuntimeException("Archivo FAOSTAT no encontrado para carga #{$cargaId}. Archivos FAOSTAT pendientes de entrega.");
    }

    private function buscarCol(array $fila, array $claves): mixed
    {
        foreach ($claves as $clave) {
            if (array_key_exists($clave, $fila)) {
                return $fila[$clave];
            }
        }
        $norm = array_map(fn ($k) => strtolower(preg_replace('/[^a-z0-9]/i', '', $k)), $claves);
        foreach ($fila as $k => $v) {
            if (in_array(strtolower(preg_replace('/[^a-z0-9]/i', '', (string) $k)), $norm, true)) {
                return $v;
            }
        }
        return null;
    }

    private function num(mixed $v): float
    {
        if ($v === null || $v === '') {
            return 0.0;
        }
        if (is_numeric($v)) {
            return (float) $v;
        }
        $s = str_replace([' ', ','], ['', ''], (string) $v);
        return is_numeric($s) ? (float) $s : 0.0;
    }

    private function numNull(mixed $v): ?float
    {
        return ($v === null || $v === '') ? null : $this->num($v);
    }
}

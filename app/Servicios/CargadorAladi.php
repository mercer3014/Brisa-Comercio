<?php

namespace App\Servicios;

use App\Models\CargaArchivo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Carga archivos ALADI — Rankings de productos hacia ranking_comercio.
 *
 * Cabecera esperada:
 *   N° | ÍTEM (Código SA) | DESCRIPCIÓN | VALOR (USD) | % TOTAL | VALOR ACUM. | % ACUM.
 *
 * Detecta filas confidenciales (código con guiones, ej. "87------") y
 * crea un registro en archivo_fuente para trazabilidad.
 */
class CargadorAladi
{
    private const ORG_ID = 2;

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
            $flujoId   = $this->resolverFlujo($carga->tipo_flujo);
            $paisId    = $this->resolverPaisBolivia($fuenteId);

            // Idempotencia
            DB::table('ranking_comercio')->where('archivo_id', $archivoId)->delete();

            $lote    = [];
            $leidas  = 0;
            $validas = 0;
            $errores = 0;

            foreach ($this->lector->iterarAsociativo($ruta, $ext) as $fila) {
                $leidas++;
                try {
                    $row = $this->mapearFila($fila, $archivoId, $fuenteId, $flujoId, $paisId, $carga);
                    if ($row === null) {
                        continue;
                    }
                    $lote[] = $row;
                    $validas++;

                    if (count($lote) >= 500) {
                        DB::table('ranking_comercio')->insert($lote);
                        $lote = [];
                    }
                } catch (Throwable $e) {
                    $errores++;
                }
            }

            if (! empty($lote)) {
                DB::table('ranking_comercio')->insert($lote);
            }

            $carga->update([
                'total_filas_leidas'  => $leidas,
                'total_filas_validas' => $validas,
                'total_filas_error'   => $errores,
                'estado'              => 'COMPLETADO',
            ]);
            DB::table('archivo_fuente')->where('archivo_id', $archivoId)
                ->update(['filas_detectadas' => $leidas, 'estado_revision' => 'OK']);

            $proceso->update([
                'estado'           => 'EXITOSO',
                'fecha_fin'        => now(),
                'filas_procesadas' => $leidas,
                'mensaje_log'      => "ALADI: {$validas}/{$leidas} filas en ranking_comercio.",
            ]);

            \Illuminate\Support\Facades\Artisan::call('comexhub:refrescar-vistas');
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

    private function mapearFila(
        array $fila,
        int $archivoId,
        int $fuenteId,
        ?int $flujoId,
        ?int $paisId,
        CargaArchivo $carga
    ): ?array {
        $ordinal   = $this->buscarCol($fila, ['N°', 'N', 'No', 'Nro', 'Posicion', '#']);
        $item      = $this->buscarCol($fila, ['ÍTEM (Código SA)', 'ITEM (Codigo SA)', 'ITEM', 'Codigo SA', 'Código SA', 'SA']);
        $desc      = $this->buscarCol($fila, ['DESCRIPCIÓN', 'DESCRIPCION', 'Descripcion', 'Description']);
        $valor     = $this->buscarCol($fila, ['VALOR (USD)', 'VALOR', 'Valor', 'Value', 'USD']);
        $pctTotal  = $this->buscarCol($fila, ['% TOTAL', 'TOTAL', 'PctTotal', '% del total']);
        $valAcum   = $this->buscarCol($fila, ['VALOR ACUM.', 'VALOR ACUM', 'Valor acumulado', 'ValorAcum']);
        $pctAcum   = $this->buscarCol($fila, ['% ACUM.', '% ACUM', 'PctAcum', '% acumulado']);

        $itemStr = trim((string) ($item ?? ''));
        if ($itemStr === '' || $itemStr === '0') {
            return null; // fila vacía o total
        }

        // Fila confidencial: código contiene guiones (ej. "87------")
        $esConfidencial = (bool) preg_match('/[-]{2,}/', $itemStr);

        $gestion = $carga->gestion ?? (int) date('Y');

        return [
            'organizacion_id'         => self::ORG_ID,
            'archivo_id'              => $archivoId,
            'pais_reportante_id'      => $paisId,
            'flujo_id'                => $flujoId,
            'tiempo_id'               => $this->resolverTiempo($gestion),
            'gestion'                 => $gestion,
            'producto_codigo_externo_id' => null,
            'item_codigo'             => substr($itemStr, 0, 20),
            'descripcion'             => $desc !== null ? substr(trim((string) $desc), 0, 500) : null,
            'es_confidencial'         => $esConfidencial,
            'fila_excel'              => null,
            'ordinal'                 => $ordinal !== null ? (int) $this->num($ordinal) : null,
            'valor'                   => $this->numNull($valor),
            'porcentaje_total'        => $this->numNull($pctTotal),
            'valor_acumulado'         => $this->numNull($valAcum),
            'porcentaje_acumulado'    => $this->numNull($pctAcum),
            'atributos_extra'         => null,
        ];
    }

    // -------------------------------------------------------------------------

    private function resolverFuente(): int
    {
        $id = DB::table('fuente_datos')
            ->where('organizacion_id', self::ORG_ID)
            ->where('version_nomenclatura', 'ALADI-base')
            ->value('fuente_id');

        return $id ? (int) $id : (int) DB::table('fuente_datos')->insertGetId([
            'organizacion_id'      => self::ORG_ID,
            'version_nomenclatura' => 'ALADI-base',
            'fecha_descarga'       => now()->toDateString(),
        ], 'fuente_id');
    }

    private function resolverPaisBolivia(int $fuenteId): ?int
    {
        // Buscar Bolivia en cualquier fuente por iso_alpha3
        $id = DB::table('pais')
            ->where(fn ($q) => $q->where('iso_alpha3', 'BOL')->orWhere('iso_alpha2', 'BO'))
            ->value('pais_id');

        return $id ? (int) $id : null;
    }

    private function resolverFlujo(?string $tipoFlujo): ?int
    {
        if (! $tipoFlujo || $tipoFlujo === 'ALADI_RANKING') {
            return null;
        }
        $codigo = $tipoFlujo === 'EXPORTACION' ? '1' : '2';
        return (int) DB::table('flujo_comercial')->where('codigo_flujo', $codigo)->value('flujo_id');
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

    private function crearArchivoFuente(CargaArchivo $carga, string $ruta): int
    {
        return (int) DB::table('archivo_fuente')->insertGetId([
            'organizacion_id'     => self::ORG_ID,
            'pais_reportante_id'  => null,
            'gestion'             => $carga->gestion,
            'ruta_archivo'        => $ruta,
            'fecha_carga'         => now(),
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
        throw new \RuntimeException("Archivo de datos no encontrado para carga #{$cargaId}.");
    }

    private function buscarCol(array $fila, array $claves): mixed
    {
        foreach ($claves as $clave) {
            if (array_key_exists($clave, $fila)) {
                return $fila[$clave];
            }
        }
        $claveNorm = array_map(fn ($k) => strtolower(preg_replace('/[^a-z0-9]/i', '', $k)), $claves);
        foreach ($fila as $k => $v) {
            $kn = strtolower(preg_replace('/[^a-z0-9]/i', '', (string) $k));
            if (in_array($kn, $claveNorm, true)) {
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
        $s = str_replace([' ', ',', '%'], ['', '', ''], (string) $v);
        return is_numeric($s) ? (float) $s : 0.0;
    }

    private function numNull(mixed $v): ?float
    {
        return ($v === null || $v === '') ? null : $this->num($v);
    }
}

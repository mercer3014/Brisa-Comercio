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
 *   Ordinal | Item | Descripción | Valor | % / Total | Valor Acumulado | % Acumulado
 *
 * Cada archivo es el "top 50" de productos de UN país miembro, para UN flujo
 * (exportaciones o importaciones) y UNA gestión. ALADI publica los valores en
 * MILES de USD: aquí se convierten a USD (x1000) para que toda la aplicacion
 * hable la misma unidad que INE y MERCOSUR.
 *
 * Detecta filas confidenciales (código con guiones, ej. "87------") y
 * crea un registro en archivo_fuente para trazabilidad.
 */
class CargadorAladi
{
    private const ORG_ID = 2;

    /** Miles de USD -> USD. */
    private const FACTOR_USD = 1000;

    /**
     * Países miembros de ALADI tal como vienen nombradas las carpetas del
     * dataset: nombre propio, ISO numérico, alpha-2 y alpha-3.
     */
    private const PAISES = [
        'ARGENTINA' => ['Argentina', 32,  'AR', 'ARG'],
        'BOLIVIA'   => ['Bolivia',   68,  'BO', 'BOL'],
        'BRASIL'    => ['Brasil',    76,  'BR', 'BRA'],
        'CHILE'     => ['Chile',     152, 'CL', 'CHL'],
        'COLOMBIA'  => ['Colombia',  170, 'CO', 'COL'],
        'CUBA'      => ['Cuba',      192, 'CU', 'CUB'],
        'ECUADOR'   => ['Ecuador',   218, 'EC', 'ECU'],
        'MEXICO'    => ['México',    484, 'MX', 'MEX'],
        'PANAMA'    => ['Panamá',    591, 'PA', 'PAN'],
        'PARAGUAY'  => ['Paraguay',  600, 'PY', 'PRY'],
        'PERU'      => ['Perú',      604, 'PE', 'PER'],
        'URUGUAY'   => ['Uruguay',   858, 'UY', 'URY'],
        'VENEZUELA' => ['Venezuela', 862, 'VE', 'VEN'],
    ];

    public function __construct(private LectorArchivo $lector)
    {
    }

    /**
     * @param string|null $rutaDirecta    leer este archivo del disco en vez del storage de la carga
     * @param bool        $refrescarVistas refrescar las vistas materializadas al terminar (en lote se hace una sola vez al final)
     * @param string|null $paisReportante  nombre de la carpeta del país (ej. "ARGENTINA"); null = Bolivia (carga manual desde el panel)
     */
    public function cargar(
        CargaArchivo $carga,
        ?string $rutaDirecta = null,
        bool $refrescarVistas = true,
        ?string $paisReportante = null
    ): void {
        $proceso = $carga->procesos()->create([
            'estado'       => 'EN_EJECUCION',
            'fecha_inicio' => now(),
        ]);

        try {
            $ruta = $rutaDirecta ?? $this->resolverRuta($carga->carga_id);
            $ext  = pathinfo($ruta, PATHINFO_EXTENSION);
            $carga->update(['estado' => 'PROCESANDO']);

            $fuenteId  = $this->resolverFuente();
            $flujoId   = $this->resolverFlujo($carga->tipo_flujo);
            $paisId    = $paisReportante !== null
                ? $this->resolverPaisReportante($paisReportante, $fuenteId)
                : $this->resolverPaisBolivia();
            $archivoId = $this->crearArchivoFuente($carga, $ruta, $paisId);

            // Idempotencia
            DB::table('ranking_comercio')->where('archivo_id', $archivoId)->delete();

            $lote    = [];
            $leidas  = 0;
            $validas = 0;
            $errores = 0;

            foreach ($this->lector->iterarAsociativo($ruta, $ext) as $fila) {
                $leidas++;
                try {
                    $row = $this->mapearFila($fila, $archivoId, $flujoId, $paisId, $carga, $leidas + 1);
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

            if ($refrescarVistas) {
                \Illuminate\Support\Facades\Artisan::call('comexhub:refrescar-vistas');
            }
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
        ?int $flujoId,
        ?int $paisId,
        CargaArchivo $carga,
        int $filaExcel
    ): ?array {
        $ordinal   = $this->buscarCol($fila, ['Ordinal', 'N°', 'N', 'No', 'Nro', 'Posicion', '#']);
        $item      = $this->buscarCol($fila, ['Item', 'ÍTEM (Código SA)', 'ITEM (Codigo SA)', 'ITEM', 'Codigo SA', 'Código SA', 'SA']);
        $desc      = $this->buscarCol($fila, ['DESCRIPCIÓN', 'DESCRIPCION', 'Descripcion', 'Description']);
        $valor     = $this->buscarCol($fila, ['VALOR (USD)', 'VALOR', 'Valor', 'Value', 'USD']);
        $pctTotal  = $this->buscarCol($fila, ['% / Total', '% TOTAL', 'TOTAL', 'PctTotal', '% del total']);
        $valAcum   = $this->buscarCol($fila, ['VALOR ACUM.', 'VALOR ACUM', 'Valor acumulado', 'ValorAcum']);
        $pctAcum   = $this->buscarCol($fila, ['% ACUM.', '% ACUM', 'PctAcum', '% acumulado', '% Acumulado']);

        $itemStr = trim((string) ($item ?? ''));
        if ($itemStr === '' || $itemStr === '0') {
            return null; // fila vacía o total
        }

        // Fila confidencial: código contiene guiones (ej. "87------")
        $esConfidencial = (bool) preg_match('/[-]{2,}/', $itemStr);

        $gestion = $carga->gestion ?? (int) date('Y');

        $valorUsd = $this->numNull($valor);
        $acumUsd  = $this->numNull($valAcum);

        return [
            'organizacion_id'         => self::ORG_ID,
            'archivo_id'              => $archivoId,
            'pais_reportante_id'      => $paisId,
            'flujo_id'                => $flujoId,
            'tiempo_id'               => $this->resolverTiempo($gestion),
            'gestion'                 => $gestion,
            'producto_codigo_externo_id' => null,
            'item_codigo'             => substr($itemStr, 0, 20),
            'descripcion'             => $desc !== null ? substr(trim(preg_replace('/\s+/', ' ', (string) $desc)), 0, 500) : null,
            'es_confidencial'         => $esConfidencial,
            'fila_excel'              => $filaExcel,
            'ordinal'                 => $ordinal !== null ? (int) $this->num($ordinal) : null,
            'valor'                   => $valorUsd !== null ? $valorUsd * self::FACTOR_USD : null,
            'porcentaje_total'        => $this->numNull($pctTotal),
            'valor_acumulado'         => $acumUsd !== null ? $acumUsd * self::FACTOR_USD : null,
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

    /** Resuelve (o crea) el país miembro dentro de la fuente ALADI. */
    private function resolverPaisReportante(string $nombreCarpeta, int $fuenteId): int
    {
        $clave = strtoupper(trim($nombreCarpeta));
        if (! isset(self::PAISES[$clave])) {
            throw new \RuntimeException("Pais ALADI no reconocido: {$nombreCarpeta}");
        }
        [$nombre, $codigo, $iso2, $iso3] = self::PAISES[$clave];

        $id = DB::table('pais')
            ->where('fuente_id', $fuenteId)
            ->where('iso_alpha3', $iso3)
            ->value('pais_id');
        if ($id) {
            return (int) $id;
        }

        // ALADI no maneja zonas geoeconomicas: los países cuelgan de "Sin zona".
        $zonaId = DB::table('zona_geoeconomica')
            ->where('fuente_id', $fuenteId)
            ->where('codigo_zona', 0)
            ->value('zona_id');
        if (! $zonaId) {
            $zonaId = (int) DB::table('zona_geoeconomica')->insertGetId([
                'fuente_id'   => $fuenteId,
                'codigo_zona' => 0,
                'descripcion' => 'Sin zona',
            ], 'zona_id');
        }

        return (int) DB::table('pais')->insertGetId([
            'fuente_id'   => $fuenteId,
            'zona_id'     => $zonaId,
            'codigo_pais' => $codigo,
            'nombre'      => $nombre,
            'iso_alpha2'  => $iso2,
            'iso_alpha3'  => $iso3,
        ], 'pais_id');
    }

    /** Compatibilidad con la carga manual desde el panel (archivos de Bolivia). */
    private function resolverPaisBolivia(): ?int
    {
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

    private function crearArchivoFuente(CargaArchivo $carga, string $ruta, ?int $paisId): int
    {
        // ruta_archivo es UNIQUE en archivo_fuente: al recargar el mismo archivo
        // se reutiliza su registro (y la idempotencia por archivo_id borra las
        // filas previas del ranking antes de reinsertar).
        $existente = DB::table('archivo_fuente')->where('ruta_archivo', $ruta)->value('archivo_id');
        if ($existente) {
            DB::table('archivo_fuente')->where('archivo_id', $existente)->update([
                'pais_reportante_id' => $paisId,
                'gestion'            => $carga->gestion,
                'fecha_carga'        => now(),
            ]);

            return (int) $existente;
        }

        return (int) DB::table('archivo_fuente')->insertGetId([
            'organizacion_id'     => self::ORG_ID,
            'pais_reportante_id'  => $paisId,
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

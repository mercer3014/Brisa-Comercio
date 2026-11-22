<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Definición de las plantillas Excel de carga, UNA por entidad y flujo.
 *
 * Las columnas salen de lo que reconoce cada Cargador (app/Servicios/Cargador*.php):
 * el primer nombre de cada columna es el que se escribe en la plantilla y los
 * "sinonimos" son los otros nombres que el cargador también acepta. Si se cambia
 * un cargador, hay que actualizar esta clase (y viceversa).
 *
 * Se usa para:
 *  - generar la plantilla (.xlsx) que se descarga desde el panel;
 *  - marcar en la previsualización qué columnas del archivo reconoce el cargador
 *    y cuáles obligatorias faltan.
 */
class PlantillasCarga
{
    /**
     * Combinaciones válidas organización (sigla) => tipos de flujo de carga_archivo.
     */
    public const FLUJOS_POR_SIGLA = [
        Organizaciones::INE      => ['EXPORTACION', 'IMPORTACION'],
        Organizaciones::ALADI    => ['EXPORTACION', 'IMPORTACION'],
        Organizaciones::MERCOSUR => ['MERCOSUR_PAIS', 'MERCOSUR_ITEM'],
        Organizaciones::FAOSTAT  => ['FAOSTAT'],
    ];

    /**
     * Extensiones que el lector de cada organización realmente sabe leer.
     * INE usa el lector XLSX de OpenSpout (solo .xlsx/.xlsm). ALADI usa
     * LectorArchivo pero su cargador solo busca datos.xlsx/xlsm/csv. MERCOSUR y
     * FAOSTAT aceptan además .xls (PhpSpreadsheet).
     */
    public const EXTENSIONES_POR_SIGLA = [
        Organizaciones::INE      => ['xlsx', 'xlsm'],
        Organizaciones::ALADI    => ['xlsx', 'xlsm', 'csv'],
        Organizaciones::MERCOSUR => ['xlsx', 'xlsm', 'xls', 'csv'],
        Organizaciones::FAOSTAT  => ['xlsx', 'xlsm', 'xls', 'csv'],
    ];

    /** Rutas /admin/cargas/plantilla/{entidad}/{flujo} => (sigla, tipo_flujo). */
    public const RUTAS = [
        'ine/exportacion'  => [Organizaciones::INE, 'EXPORTACION'],
        'ine/importacion'  => [Organizaciones::INE, 'IMPORTACION'],
        'aladi/exportacion' => [Organizaciones::ALADI, 'EXPORTACION'],
        'aladi/importacion' => [Organizaciones::ALADI, 'IMPORTACION'],
        'mercosur/pais'    => [Organizaciones::MERCOSUR, 'MERCOSUR_PAIS'],
        'mercosur/item'    => [Organizaciones::MERCOSUR, 'MERCOSUR_ITEM'],
        'faostat/indices'  => [Organizaciones::FAOSTAT, 'FAOSTAT'],
    ];

    public static function rutaDe(string $sigla, string $tipoFlujo): ?string
    {
        foreach (self::RUTAS as $ruta => [$s, $f]) {
            if ($s === $sigla && $f === $tipoFlujo) {
                return $ruta;
            }
        }

        return null;
    }

    /** Lista para el frontend: [{ruta, sigla, tipo_flujo, titulo}]. */
    public static function catalogo(): array
    {
        $lista = [];
        foreach (self::RUTAS as $ruta => [$sigla, $flujo]) {
            $def = self::definicion($sigla, $flujo);
            $lista[] = [
                'ruta'       => $ruta,
                'sigla'      => $sigla,
                'tipo_flujo' => $flujo,
                'titulo'     => $def['titulo'],
                'archivo'    => $def['archivo'],
            ];
        }

        return $lista;
    }

    /**
     * @return array{titulo:string, archivo:string, unidad:string, notas:string[], columnas:array<int, array>}
     */
    public static function definicion(string $sigla, string $tipoFlujo): ?array
    {
        return match ("{$sigla}|{$tipoFlujo}") {
            Organizaciones::INE.'|EXPORTACION'      => self::ine(true),
            Organizaciones::INE.'|IMPORTACION'      => self::ine(false),
            Organizaciones::ALADI.'|EXPORTACION'    => self::aladi(true),
            Organizaciones::ALADI.'|IMPORTACION'    => self::aladi(false),
            Organizaciones::MERCOSUR.'|MERCOSUR_PAIS' => self::mercosurPais(),
            Organizaciones::MERCOSUR.'|MERCOSUR_ITEM' => self::mercosurItem(),
            Organizaciones::FAOSTAT.'|FAOSTAT'      => self::faostat(),
            default => null,
        };
    }

    /** Normalización común: sin tildes, sin símbolos ni espacios, en mayúsculas. */
    public static function normalizar(string $s): string
    {
        return strtoupper(preg_replace('/[^a-z0-9]/i', '', Str::ascii($s)));
    }

    /**
     * Compara las cabeceras de un archivo con la definición.
     *
     * @return array{columnas: array<int, array{origen:string, reconocida:bool, campo:?string}>, faltan: string[], definidas:int}
     */
    public static function reconocer(string $sigla, string $tipoFlujo, array $cabeceras): array
    {
        $def = self::definicion($sigla, $tipoFlujo);
        if (! $def) {
            return ['columnas' => [], 'faltan' => [], 'definidas' => 0];
        }

        $indice = [];
        foreach ($def['columnas'] as $col) {
            foreach (array_merge([$col['nombre']], $col['sinonimos'] ?? []) as $alias) {
                $indice[self::normalizar($alias)] ??= $col['nombre'];
            }
        }

        $hallados = [];
        $resultado = [];
        foreach ($cabeceras as $cab) {
            $campo = $indice[self::normalizar((string) $cab)] ?? null;
            // Una misma columna lógica solo se reconoce una vez (el cargador toma la primera).
            if ($campo !== null && isset($hallados[$campo])) {
                $campo = null;
            }
            if ($campo !== null) {
                $hallados[$campo] = true;
            }
            $resultado[] = ['origen' => (string) $cab, 'reconocida' => $campo !== null, 'campo' => $campo];
        }

        $faltan = [];
        foreach ($def['columnas'] as $col) {
            if (($col['obligatoria'] ?? false) && ! isset($hallados[$col['nombre']])) {
                $faltan[] = $col['nombre'];
            }
        }

        return ['columnas' => $resultado, 'faltan' => $faltan, 'definidas' => count($def['columnas'])];
    }

    // ---------------------------------------------------------------------
    //  Definiciones (derivadas del código de cada cargador)
    // ---------------------------------------------------------------------

    private static function c(string $nombre, string $descripcion, string $formato, array $ejemplos, bool $obligatoria = false, array $sinonimos = []): array
    {
        return compact('nombre', 'descripcion', 'formato', 'ejemplos', 'obligatoria', 'sinonimos');
    }

    /** CargadorIne::mapearFila / resolver*. */
    private static function ine(bool $export): array
    {
        $cols = [
            self::c('GESTION', 'Año (gestión) de la operación.', 'Número entero de 4 dígitos (1980-2100).', [2025, 2025], true, ['ANIO', 'AÑO', 'ANO']),
            self::c('MES', 'Mes de la operación. Vacío o 0 = registro ANUAL (sin mes).', 'Entero 0-12.', [3, 0]),
            self::c('NANDINA', 'Código arancelario NANDINA a 10 dígitos.', 'TEXTO de 10 dígitos con ceros a la izquierda (ej. 0901110000). Formatee la columna como Texto antes de pegar; si Excel quita los ceros, el sistema completa el código a 10 dígitos.', ['0901110000', '2616100000'], true, ['PARTIDA']),
            self::c('DESNAN', 'Descripción del producto (NANDINA).', 'Texto.', ['Café sin tostar, sin descafeinar', 'Minerales de plata y sus concentrados'], false, ['DESCRIPCION', 'GLOSA']),
            self::c('SECC', 'Código de la sección arancelaria (I-XXI en número).', 'Entero 1-21.', [2, 5]),
            self::c('DESSEC', 'Descripción de la sección.', 'Texto.', ['Productos del reino vegetal', 'Productos minerales']),
            self::c('CAP', 'Capítulo arancelario (2 dígitos). Si falta, se toma de los 2 primeros dígitos del NANDINA.', 'Entero 1-99.', [9, 26]),
            self::c('DESCAP', 'Descripción del capítulo.', 'Texto.', ['Café, té, yerba mate y especias', 'Minerales metalíferos, escorias y cenizas']),
            self::c('PAIS', $export ? 'Código INE del país de destino.' : 'Código INE del país de origen.', 'Entero (código del INE, no ISO).', [840, 76], false, ['CODPAIS']),
            self::c('DESPAIS', 'Nombre del país.', 'Texto.', ['Estados Unidos', 'Brasil'], false, ['DESPAI']),
            self::c('AREA', 'Código de zona/área económica del INE.', 'Entero.', [2, 1]),
            self::c('DESAREA', 'Descripción de la zona económica.', 'Texto.', ['América del Norte', 'MERCOSUR'], false, ['DESZON', 'DESZONA']),
            self::c('DEPART', 'Código del departamento de Bolivia.', 'Entero 1-9.', [7, 2], false, ['DEPTO', 'DEPARTAMENTO']),
            self::c('DESDEP', 'Nombre del departamento.', 'Texto.', ['Santa Cruz', 'La Paz'], false, ['DESDEPTO']),
            self::c('ADUANA', 'Código de la aduana.', 'Entero.', [701, 201], false, ['ADUDES']),
            self::c('DESADU', 'Nombre de la aduana.', 'Texto.', ['Aeropuerto Viru Viru', 'El Alto']),
            self::c('MEDI', 'Código del medio de transporte.', 'Entero.', [4, 3], false, ['MEDIO']),
            self::c('DESMEDI', 'Descripción del medio de transporte.', 'Texto.', ['Aéreo', 'Carretero'], false, ['DESMED']),
            self::c($export ? 'VIASAL' : 'VIA', $export ? 'Código de la vía de salida.' : 'Código de la vía de ingreso.', 'Entero.', [4, 3], false, $export ? ['VIA'] : ['VIASAL']),
            self::c('DESVIA', 'Descripción de la vía.', 'Texto.', ['Aérea', 'Carretera']),
            self::c('CUCI3', 'Código CUCI Rev.3.', 'Texto/código.', ['07111', '28910'], false, ['CUCIR3', 'CUCI']),
            self::c('DESCUCI3', 'Descripción CUCI.', 'Texto.', ['Café sin tostar', 'Minerales de metales preciosos'], false, ['DESCUCI']),
            self::c('GCE3', 'Código de Grandes Categorías Económicas Rev.3.', 'Texto/código.', ['111', '21'], false, ['GCER3', 'GCE']),
            self::c('DESGCE3', 'Descripción GCE.', 'Texto.', ['Alimentos básicos', 'Suministros industriales'], false, ['DESGCE']),
            self::c('CIIUR3', 'Código de actividad CIIU Rev.3.', 'Texto/código (hasta 5 caracteres).', ['0113', '1320'], false, ['CIIU']),
            self::c('DESCIIU3', 'Descripción CIIU.', 'Texto.', ['Cultivo de café', 'Extracción de minerales metalíferos'], false, ['DESCIIU']),
            self::c('TNT', 'Código Tradicional / No tradicional.', 'Entero.', [2, 1]),
            self::c('DESTNT', 'Descripción del producto TNT.', 'Texto.', ['Café', 'Plata']),
            self::c('CLTNT', 'Clase TNT (Tradicional / No tradicional).', 'Texto.', ['No tradicional', 'Tradicional']),
            self::c('CUODE', 'Código CUODE (Clasificación por uso o destino económico).', 'Entero/código.', [11, 21]),
            self::c('DESCUO', 'Descripción CUODE.', 'Texto.', ['Bienes de consumo no duradero', 'Materias primas'], false, ['DESCUODE']),
            self::c('KILBRU', 'Peso bruto.', 'Número, en kilogramos.', [12500.5, 30000], false, $export ? [] : ['KILOS']),
        ];

        if ($export) {
            $cols[] = self::c('KILNET', 'Peso neto.', 'Número, en kilogramos.', [12000, 29500]);
            $cols[] = self::c('FINO', 'Peso fino (minerales).', 'Número, en kilogramos.', [null, 450.25], false, ['PFINO']);
            $cols[] = self::c('VALOR', 'Valor FOB de la exportación.', 'Número, en DÓLARES (USD), sin separador de miles.', [48250.75, 210000], true, ['FOB']);
        } else {
            $cols[] = self::c('FOB', 'Valor FOB de la importación.', 'Número, en dólares (USD).', [48250.75, 210000], true);
            $cols[] = self::c('FRO', 'Valor CIF en frontera.', 'Número, en dólares (USD).', [50100, 215500]);
            $cols[] = self::c('ADU', 'Valor CIF en aduana.', 'Número, en dólares (USD).', [50400, 216000]);
            $cols[] = self::c('PAG', 'Gravámenes pagados.', 'Número, en dólares (USD).', [2500, 10800]);
        }

        return [
            'titulo'  => 'INE · '.($export ? 'Exportaciones' : 'Importaciones'),
            'archivo' => 'plantilla_INE_'.($export ? 'exportaciones' : 'importaciones').'.xlsx',
            'unidad'  => 'Valores en dólares (USD); pesos en kilogramos.',
            'notas'   => [
                'Una fila por operación (microdato) con el formato de la base del INE.',
                'Obligatorias: GESTION, NANDINA y '.($export ? 'VALOR' : 'FOB').'. Las demás columnas son opcionales pero recomendadas: si faltan, la dimensión queda como "Sin dato".',
                'MES vacío o 0 = dato ANUAL (sin desagregación mensual). Use 1-12 para datos mensuales.',
                'NANDINA va como TEXTO con sus 10 dígitos y ceros a la izquierda (0901110000). Formatee la columna como Texto en Excel antes de pegar.',
                'Al cargar se REEMPLAZAN las filas de cada gestión del archivo para este flujo (no se duplican). Si indica la gestión en el formulario, debe coincidir con la del archivo.',
                'Formato admitido para INE: .xlsx o .xlsm (no .xls ni .csv). Las filas sin GESTION o sin NANDINA se omiten y se registran en Calidad.',
            ],
            'columnas' => $cols,
        ];
    }

    /** CargadorAladi::mapearFila. */
    private static function aladi(bool $export): array
    {
        return [
            'titulo'  => 'ALADI · Ranking de '.($export ? 'exportaciones' : 'importaciones'),
            'archivo' => 'plantilla_ALADI_ranking_'.($export ? 'exportaciones' : 'importaciones').'.xlsx',
            'unidad'  => 'Valores en MILES de dólares (miles de USD), tal como publica ALADI/SICOEX. El sistema los multiplica por 1.000.',
            'notas'   => [
                'Un archivo = el ranking de productos de Bolivia para UN flujo y UNA gestión (desde el panel el país reportante es Bolivia).',
                'La GESTIÓN se indica en el formulario de carga (es obligatoria para ALADI); el archivo no la trae.',
                'Valor y Valor acumulado van en MILES de USD (ej. 1250,5 = 1.250.500 USD).',
                'Item: código SA/NALADISA como TEXTO. Los ítems confidenciales con guiones (ej. 87------) se aceptan y se marcan como confidenciales.',
                'Las filas de total (Item vacío o 0) se ignoran.',
                'Recargar el mismo flujo y gestión REEMPLAZA el ranking anterior (no duplica).',
            ],
            'columnas' => [
                self::c('Ordinal', 'Posición en el ranking.', 'Entero 1, 2, 3…', [1, 2], false, ['N°', 'Nro', 'Posicion', '#']),
                self::c('Item', 'Código del producto (SA / NALADISA).', 'TEXTO, con ceros a la izquierda.', ['26161000', '0901'], true, ['ITEM (Codigo SA)', 'Codigo SA', 'SA']),
                self::c('Descripcion', 'Descripción del producto.', 'Texto.', ['Minerales de plata y sus concentrados', 'Café']),
                self::c('Valor', 'Valor del flujo en la gestión.', 'Número en MILES de USD.', [1250.5, 830.25], true, ['VALOR (USD)', 'Value']),
                self::c('% / Total', 'Participación en el total del flujo.', 'Número 0-100 (sin el signo %).', [12.4, 8.2], false, ['% TOTAL', '% del total']),
                self::c('Valor acumulado', 'Valor acumulado hasta esta posición.', 'Número en MILES de USD.', [1250.5, 2080.75], false, ['VALOR ACUM.', 'VALOR ACUM']),
                self::c('% Acumulado', 'Participación acumulada.', 'Número 0-100 (sin el signo %).', [12.4, 20.6], false, ['% ACUM.', '% ACUM']),
            ],
        ];
    }

    /** CargadorMercosurPais::mapearFila. */
    private static function mercosurPais(): array
    {
        return [
            'titulo'  => 'MERCOSUR · Comercio por país',
            'archivo' => 'plantilla_MERCOSUR_por_pais.xlsx',
            'unidad'  => 'Valores en dólares (USD); volúmenes en kilogramos.',
            'notas'   => [
                'Una fila por país socio y año, para UNA zona geoeconómica.',
                'La ZONA se toma del NOMBRE DEL ARCHIVO (ej. "Union Europea.xlsx", "Alianza del Pacifico.xlsx", "Asia.xlsx"). Renombre el archivo antes de subirlo.',
                'Recargar la misma zona REEMPLAZA los años que trae el archivo (no duplica). Un mismo país no puede repetirse en la misma zona y año.',
                'ISO 3166: código numérico (ej. 076) o alfa-2/alfa-3 (BR / BRA). Se necesita ISO 3166 o País.',
                'Filas sin código (zonas francas, "Desconocido"): deje ISO 3166 vacío y escriba el nombre; se reconocen por nombre. Cada país se vincula al catálogo ISO 3166 (pais_iso) y, si no se reconoce, queda una advertencia en Calidad.',
            ],
            'columnas' => [
                self::c('ISO 3166', 'Código ISO 3166 del país socio.', 'Numérico de 3 dígitos o alfa-2/alfa-3, como TEXTO.', ['076', '032'], false, ['ISO3166', 'ISO']),
                self::c('País', 'Nombre del país socio.', 'Texto.', ['Brasil', 'Argentina'], false, ['Pais', 'Country', 'Socio']),
                self::c('Año', 'Año del dato.', 'Entero 1990-2100.', [2024, 2024], true, ['Ano', 'Year']),
                self::c('Exportaciones', 'Exportaciones hacia el país.', 'Número en USD (≥ 0).', [1500000, 820000], false, ['Exports', 'Export']),
                self::c('Importaciones (FOB)', 'Importaciones FOB desde el país.', 'Número en USD (≥ 0).', [900000, 450000], false, ['Importaciones FOB', 'Imports FOB']),
                self::c('Importaciones (CIF)', 'Importaciones CIF desde el país.', 'Número en USD (≥ 0).', [950000, 470000], false, ['Importaciones CIF', 'Imports CIF', 'Importaciones']),
                self::c('Volumen Exports', 'Volumen exportado.', 'Número en kg.', [250000, 120000], false, ['Vol Exports', 'Volumen Export']),
                self::c('Volumen Imports', 'Volumen importado.', 'Número en kg.', [180000, 90000], false, ['Vol Imports', 'Volumen Import']),
            ],
        ];
    }

    /** CargadorMercosurItem::mapearFila. */
    private static function mercosurItem(): array
    {
        return [
            'titulo'  => 'MERCOSUR · Comercio por ítem (NCM)',
            'archivo' => 'plantilla_MERCOSUR_por_item.xlsx',
            'unidad'  => 'Valores en dólares (USD); volúmenes en kilogramos.',
            'notas'   => [
                'Una fila por ítem NCM y año, para UNA zona geoeconómica.',
                'La ZONA se toma del NOMBRE DEL ARCHIVO (ej. "Union Europea.xlsx"). Renombre el archivo antes de subirlo.',
                'NCM como TEXTO con ceros a la izquierda (ej. 09011110).',
                'Recargar la misma zona REEMPLAZA los años que trae el archivo (no duplica). Un mismo NCM no puede repetirse en la misma zona y año.',
            ],
            'columnas' => [
                self::c('NCM', 'Código NCM del ítem.', 'TEXTO (hasta 12 caracteres), con ceros a la izquierda.', ['09011110', '26161000'], true, ['Código NCM', 'Codigo NCM', 'Item NCM', 'Item']),
                self::c('Descripción', 'Descripción del ítem.', 'Texto.', ['Café sin tostar, en grano', 'Minerales de plata'], false, ['Descripcion', 'Description']),
                self::c('Año', 'Año del dato.', 'Entero 1990-2100.', [2024, 2024], true, ['Ano', 'Year']),
                self::c('Exportaciones', 'Exportaciones del ítem.', 'Número en USD (≥ 0).', [350000, 1200000], false, ['Exports', 'Export']),
                self::c('Importaciones (FOB)', 'Importaciones FOB.', 'Número en USD (≥ 0).', [0, 15000], false, ['Importaciones FOB', 'Imports FOB']),
                self::c('Importaciones (CIF)', 'Importaciones CIF.', 'Número en USD (≥ 0).', [0, 16000], false, ['Importaciones CIF', 'Imports CIF', 'Importaciones']),
                self::c('Volumen Exports', 'Volumen exportado.', 'Número en kg.', [80000, 400000], false, ['Vol Exports', 'Volumen Export']),
                self::c('Volumen Imports', 'Volumen importado.', 'Número en kg.', [0, 2000], false, ['Vol Imports', 'Volumen Import']),
            ],
        ];
    }

    /** CargadorFaostat::mapearFila (formato largo del dominio TI de FAOSTAT). */
    private static function faostat(): array
    {
        return [
            'titulo'  => 'FAOSTAT · Índices comerciales',
            'archivo' => 'plantilla_FAOSTAT_indices.xlsx',
            'unidad'  => 'Índices (base 2014-2016 = 100), NO dólares. La unidad se toma de la columna Unidad.',
            'notas'   => [
                'Formato largo de la descarga masiva de FAOSTAT (dominio TI). Puede subir la descarga tal cual (.csv, .xls o .xlsx).',
                'Obligatorias: Área, Elemento, Año y Valor. Se ignoran las filas con año fuera de 1950-2100.',
                'Código del área (M49) y Código del producto (CPC) como TEXTO (respete ceros a la izquierda).',
            ],
            'columnas' => [
                self::c('Código del ámbito', 'Código del dominio FAOSTAT.', 'Texto.', ['TI', 'TI']),
                self::c('Ámbito', 'Nombre del dominio.', 'Texto.', ['Índices comerciales', 'Índices comerciales']),
                self::c('Código del área (M49)', 'Código M49 del país.', 'TEXTO de 3 dígitos.', ['068', '068'], false, ['Codigo del area (M49)', 'Área Código', 'Area Code']),
                self::c('Área', 'Nombre del país.', 'Texto.', ['Bolivia (Estado Plurinacional de)', 'Bolivia (Estado Plurinacional de)'], true, ['Area']),
                self::c('Código del elemento', 'Código del elemento.', 'Texto/entero.', ['462', '492'], false, ['Codigo del elemento', 'Element Code']),
                self::c('Elemento', 'Nombre del índice.', 'Texto.', ['Índice del valor de las exportaciones', 'Índice del valor de las importaciones'], true, ['Element']),
                self::c('Código del producto (CPC)', 'Código CPC del producto.', 'TEXTO.', ['F1882', 'F1882'], false, ['Codigo del producto (CPC)', 'Item Code (CPC)']),
                self::c('Producto', 'Nombre del producto.', 'Texto.', ['Productos agrícolas', 'Productos agrícolas'], false, ['Item']),
                self::c('Código del año', 'Código del año (igual al año).', 'Entero.', [2022, 2022]),
                self::c('Año', 'Año del dato.', 'Entero 1950-2100.', [2022, 2022], true, ['Ano', 'Year']),
                self::c('Unidad', 'Unidad del valor.', 'Texto.', ['índice', 'índice'], false, ['Unit']),
                self::c('Valor', 'Valor del índice.', 'Número.', [185.3, 142.7], true, ['Value']),
                self::c('Símbolo', 'Símbolo de calidad del dato (E = estimado, A = oficial…).', 'Texto.', ['E', 'A'], false, ['Simbolo', 'Flag']),
                self::c('Descripción del Símbolo', 'Significado del símbolo.', 'Texto.', ['Valor estimado', 'Cifra oficial'], false, ['Descripcion del Simbolo', 'Flag Description']),
            ],
        ];
    }
}

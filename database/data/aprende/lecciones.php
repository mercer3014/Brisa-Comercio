<?php

/**
 * Lecciones de la ruta "Aprende comercio exterior" (mejora 1).
 *
 * Contenido estático y versionado (no hace falta tabla todavía). Cada lección
 * tiene dos versiones de texto (mejora 6): 'colegio' y 'universidad'.
 *
 * Marcas que entiende el frontend / backend:
 *   [[clave]] o [[clave|texto]]  término del glosario (GdGlosario).
 *   {token:args}                 cifra calculada EN VIVO desde brisa por
 *                                DatosEducativos::resolver(). Nunca se escriben
 *                                números a mano en este archivo.
 *
 * 'grafico' es el id de la serie que arma DatosEducativos::grafico().
 */
return [
    [
        'slug' => 'exportar-e-importar',
        'titulo' => 'Qué es exportar y qué es importar',
        'resumen' => 'Lo que Bolivia vende y lo que compra al resto del mundo, con los totales de una gestión real.',
        'minutos' => 5,
        'grafico' => 'kpis_flujos',
        'gestion_param' => true,
        'parrafos' => [
            'colegio' => [
                'Cuando una empresa boliviana vende soya, zinc u oro a otro país, Bolivia [[exportacion|exporta]]. Cuando una tienda trae celulares, autos o medicinas desde afuera, Bolivia [[importacion|importa]].',
                'El [[ine|INE]] registra cada una de esas operaciones. Geodata las suma por año (cada año se llama [[gestion|gestión]]). Por ejemplo, en {defecto} Bolivia exportó {exp:defecto}.',
                'Mira el gráfico: a la izquierda está lo que se vendió y a la derecha lo que se compró. Si un lado dice "Sin datos" no es un cero: es que esa información todavía no se cargó. Hoy hay importaciones cargadas para {cob_imp}.',
            ],
            'universidad' => [
                'La [[exportacion|exportación]] registra la salida de mercancías del territorio aduanero y la [[importacion|importación]] su ingreso. El [[ine|INE]] publica microdatos por operación con partida [[nandina|NANDINA]], país, medio de transporte, departamento y valor.',
                'Por regla única del sistema, las exportaciones se valoran [[fob|FOB]] y las importaciones [[cif|CIF frontera]]. En {defecto} la exportación FOB fue {exp:defecto}.',
                'Cobertura actual de la base: exportaciones {cob_exp}; importaciones {cob_imp}. Una gestión sin un flujo se muestra como "Sin datos", nunca como cero, para no sesgar indicadores como la [[balanza|balanza comercial]].',
            ],
        ],
        'pregunta' => [
            'colegio' => 'Mira los cinco productos que más vendió Bolivia en la gestión elegida. ¿Cuáles salen de la tierra (minería, gas) y cuáles del campo?',
            'universidad' => 'Compara la composición de exportaciones e importaciones de una misma gestión con ambos flujos (por ejemplo 1999). ¿Qué te dice sobre la estructura productiva del país?',
        ],
        'explorar' => [
            ['texto' => 'Ver el panorama en Inicio', 'ruta' => '/'],
            ['texto' => 'Productos exportados', 'ruta' => '/productos'],
        ],
    ],
    [
        'slug' => 'fob-y-cif',
        'titulo' => 'FOB y CIF: dos formas de medir el valor',
        'resumen' => 'Por qué una importación vale más en la frontera que en el puerto de salida.',
        'minutos' => 6,
        'grafico' => 'fob_cif',
        'gestion_param' => false,
        'parrafos' => [
            'colegio' => [
                '[[fob|FOB]] es el precio de la mercadería cuando sale del país que la vende, ya cargada en el transporte. [[cif|CIF]] es ese mismo precio más el seguro y el flete (el viaje) hasta llegar.',
                'Bolivia no tiene mar, así que lo que compra viaja un buen trecho hasta nuestras fronteras. Por eso el INE mide las importaciones en CIF frontera: lo que cuesta la mercadería puesta en la frontera boliviana.',
                'El gráfico compara, para cada año con importaciones cargadas, el valor FOB y el valor CIF frontera de lo que Bolivia compró. La diferencia es, sobre todo, transporte y seguro. Por ejemplo, en 1999 el CIF fue {cif_fob:1999} más alto que el FOB.',
            ],
            'universidad' => [
                'Los INCOTERMS definen hasta dónde paga el vendedor. [[fob|FOB]] (Free On Board) valora la mercadería a bordo en el punto de embarque; [[cif|CIF]] (Cost, Insurance and Freight) agrega seguro y flete hasta el destino.',
                'Geodata aplica una regla única: exportación y reexportación = FOB (valor_fob_usd); importación = CIF frontera (valor_cif_frontera_usd). El INE también reporta FOB en importaciones, y no se suman entre sí.',
                'La brecha CIF/FOB de las importaciones aproxima el costo de flete y seguro de un país mediterráneo. En 1999 fue de {cif_fob:1999}. El gráfico la muestra para todas las gestiones con importaciones cargadas ({cob_imp}).',
            ],
        ],
        'pregunta' => [
            'colegio' => '¿Por qué crees que traer mercadería a Bolivia cuesta más que a un país con puerto?',
            'universidad' => '¿La brecha CIF/FOB es estable entre años? ¿Qué factores (precio del petróleo, rutas, composición de lo importado) podrían moverla?',
        ],
        'explorar' => [
            ['texto' => 'Indicadores de 1999', 'ruta' => '/indicadores?gestion=1999'],
            ['texto' => 'Metodología en Acerca de', 'ruta' => '/acerca'],
        ],
    ],
    [
        'slug' => 'balanza-comercial',
        'titulo' => 'La balanza comercial',
        'resumen' => 'Exportaciones menos importaciones: cuándo Bolivia vendió más de lo que compró.',
        'minutos' => 7,
        'grafico' => 'balanza',
        'gestion_param' => false,
        'parrafos' => [
            'colegio' => [
                'La [[balanza|balanza comercial]] es una resta: lo que el país vendió menos lo que compró. Si sale positiva hay [[superavit|superávit]]; si sale negativa hay [[deficit|déficit]].',
                'En 1999 Bolivia exportó {exp:1999} e importó {imp:1999}. El [[saldo|saldo]] fue {saldo:1999}.',
                'Del 2000 en adelante todavía no están cargadas las importaciones, así que la barra del saldo aparece como "Sin datos". Las exportaciones sí están: la más alta de la serie completa es la de {max_exp_anio} ({max_exp}).',
            ],
            'universidad' => [
                'Balanza comercial de bienes = exportaciones FOB − importaciones CIF frontera (regla única del sistema). La [[tasa-cobertura|tasa de cobertura]] (X/M × 100) expresa la misma relación como porcentaje.',
                'Con importaciones cargadas solo para {cob_imp}, el saldo es computable en esos años; en 1999 fue {saldo:1999} y la tasa de cobertura {tc:1999}. En {n_deficit:1992:1999} de las {n_saldo:1992:1999} gestiones con ambos flujos (1992–1999) el saldo fue negativo.',
                'La serie de exportaciones (1992-{defecto}) muestra ciclos: variación 2014→2015 de {var_exp:2014:2015} y máximo de la serie en {max_exp_anio} ({max_exp}). Al cargar las importaciones 2000-2026 el saldo se completará solo.',
            ],
        ],
        'pregunta' => [
            'colegio' => 'Mira cómo cayeron las exportaciones de 2014 ({exp:2014}) a 2015 ({exp:2015}) y cómo {max_exp_anio} llegó a {max_exp}. ¿Qué crees que pasó en cada tramo?',
            'universidad' => 'Entre 2014 y 2015 las exportaciones variaron {var_exp:2014:2015}. ¿Qué parte de esa caída explicarías por precios internacionales y qué parte por volumen? (Pista: compara el valor unitario en /indicadores.)',
        ],
        'explorar' => [
            ['texto' => 'Línea de tiempo con el saldo', 'ruta' => '/linea-de-tiempo'],
            ['texto' => 'Indicadores (tasa de cobertura)', 'ruta' => '/indicadores?gestion=1999'],
        ],
    ],
    [
        'slug' => 'socios-comerciales',
        'titulo' => 'Socios comerciales',
        'resumen' => 'A qué países les vende Bolivia y de cuáles compra.',
        'minutos' => 5,
        'grafico' => 'socios',
        'gestion_param' => true,
        'parrafos' => [
            'colegio' => [
                'Un [[socio|socio comercial]] es un país con el que compramos y vendemos. En {defecto} el principal destino de lo que vendió Bolivia fue {destino:defecto}.',
                'Cada país se identifica con un código [[iso|ISO]] (por ejemplo BRA para Brasil). Así Geodata junta nombres distintos del mismo país, como "EE.UU." y "Estados Unidos".',
                'Cambia la gestión para ver cómo cambian los socios con los años. Para los orígenes de las importaciones elige un año entre {cob_imp}.',
            ],
            'universidad' => [
                'La concentración por destino importa: depender de pocos compradores aumenta la vulnerabilidad externa. El [[hhi|HHI]] de países de destino resume esa concentración en /indicadores.',
                'Los países se homologan a [[iso|ISO 3166-1]] (alpha-3, alpha-2 y [[m49|M49]]) mediante v_pais_canonico; zonas francas y "no declarado" quedan fuera del catálogo ISO.',
                'Destino líder en {defecto}: {destino:defecto}. Los orígenes de importación solo existen para {cob_imp}.',
            ],
        ],
        'pregunta' => [
            'colegio' => '¿Por qué crees que los países vecinos aparecen tan arriba en la lista?',
            'universidad' => 'Compara el top de destinos de una gestión del boom y una reciente. ¿La canasta de destinos se diversificó o se concentró?',
        ],
        'explorar' => [
            ['texto' => 'Ranking de países', 'ruta' => '/rankings?dimension=pais'],
            ['texto' => 'Mapa comercial', 'ruta' => '/mapa-comercial'],
        ],
    ],
    [
        'slug' => 'vias-de-transporte',
        'titulo' => 'Vías de transporte',
        'resumen' => 'Por carretera, por ducto, por avión o por un puerto ajeno: cómo salen y entran las mercaderías.',
        'minutos' => 5,
        'grafico' => 'vias',
        'gestion_param' => true,
        'parrafos' => [
            'colegio' => [
                'Bolivia no tiene costa. Lo que el INE llama "marítimo" es en realidad un viaje [[intermodal|intermodal]]: primero por carretera o tren hasta un puerto de un país vecino y después en barco.',
                'Los ductos llevan gas; los aviones llevan cosas livianas y caras, como el oro; los camiones llevan casi de todo.',
                'Ojo: el código marítimo del INE solo aparece hasta {via_hasta:maritimo}. Después de esa gestión el INE dejó de usar ese código: si ves la vía marítima vacía en años recientes, no significa que el país haya dejado de usar puertos.',
            ],
            'universidad' => [
                'Geodata agrupa los medios del INE en cuatro [[via|vías]]: marítima (intermodal MARITIMA-CARRETERA y MARITIMA-FERROVIARIA), terrestre (carretera, ferrovía), aérea (aérea y courier) y otros (ductos, fluvial, lacustre, postal).',
                'El valor por vía depende de la canasta: los hidrocarburos por ducto y el oro por vía aérea pueden mover la participación aunque el número de operaciones sea bajo.',
                'Aviso metodológico: el código marítimo-intermodal solo existe hasta {via_hasta:maritimo}; comparar participaciones antes y después de ese año exige cautela.',
            ],
        ],
        'pregunta' => [
            'colegio' => '¿Qué vía crees que usa más Bolivia para vender sus productos? Revisa el gráfico y compáralo con lo que pensabas.',
            'universidad' => '¿Cómo cambiaría la participación por vía si se excluyeran los hidrocarburos? Explora /comercio-por-via para justificarlo.',
        ],
        'explorar' => [
            ['texto' => 'Comercio por vía', 'ruta' => '/comercio-por-via'],
        ],
    ],
    [
        'slug' => 'que-vende-bolivia',
        'titulo' => 'Qué vende Bolivia: la canasta exportadora',
        'resumen' => 'Productos, partidas arancelarias y qué tan concentrada está la venta.',
        'minutos' => 6,
        'grafico' => 'canasta',
        'gestion_param' => true,
        'parrafos' => [
            'colegio' => [
                'Cada producto tiene un código llamado [[partida|partida arancelaria]]. En Bolivia se usa la nomenclatura [[nandina|NANDINA]], de 10 dígitos.',
                'En {defecto} el producto que más vendió Bolivia fue {lider:defecto}, con {lider_pct:defecto} del total.',
                'Los productos se separan en [[tnt|tradicionales]] (minerales e hidrocarburos) y no tradicionales (soya, castaña, quinua, manufacturas...). El gráfico de anillo muestra cuánto pesa cada grupo.',
            ],
            'universidad' => [
                'La canasta se analiza por partida [[nandina|NANDINA]] (10 dígitos) y por [[seccion-sa|sección del Sistema Armonizado]]. La clasificación [[tnt|TNT]] separa exportación tradicional y no tradicional.',
                'Producto líder en {defecto}: {lider:defecto} ({lider_pct:defecto} del valor FOB). El [[hhi|HHI]] de productos exportados de esa gestión fue {hhi_prod:defecto}.',
                'Un HHI alto implica dependencia de pocos bienes (y de sus precios). Compáralo entre gestiones en /indicadores.',
            ],
        ],
        'pregunta' => [
            'colegio' => 'Si el precio del producto líder bajara a la mitad, ¿qué le pasaría al total que vende Bolivia?',
            'universidad' => '¿Qué políticas podrían reducir el HHI de la canasta exportadora sin reducir el valor total?',
        ],
        'explorar' => [
            ['texto' => 'Productos (treemap NANDINA)', 'ruta' => '/productos'],
            ['texto' => 'HHI en Indicadores', 'ruta' => '/indicadores'],
        ],
    ],
];

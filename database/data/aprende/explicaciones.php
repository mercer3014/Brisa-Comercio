<?php

/**
 * Textos "¿Qué estoy viendo?" (mejora 3) por clave, con dos niveles (mejora 6).
 * Se usan en GdExplicacion en todo el portal. Contenido estático, sin IA.
 * Los términos [[clave|texto]] abren el glosario.
 */
return [
    'inicio.panorama' => [
        'colegio' => 'Estos cuadros resumen un año: cuánto vendió Bolivia ([[exportacion|exportaciones]]), cuánto compró ([[importacion|importaciones]]) y la diferencia ([[saldo|saldo]]). Si un cuadro dice "Sin datos" es porque ese año todavía no se cargó, no porque sea cero.',
        'universidad' => 'KPIs de la [[gestion|gestión]]: exportación [[fob|FOB]], importación [[cif|CIF frontera]], [[saldo|saldo]] y [[tasa-cobertura|tasa de cobertura]], con variación contra el mismo periodo del año anterior (en una [[gestion-preliminar|gestión preliminar]] se comparan los mismos meses).',
    ],
    'productos.treemap' => [
        'colegio' => 'Cada rectángulo es un producto: mientras más grande, más vendió Bolivia. Los colores agrupan productos parecidos ([[seccion-sa|secciones]]).',
        'universidad' => 'Treemap por [[partida|partida]] [[nandina|NANDINA]] coloreado por [[seccion-sa|sección del SA]]; el diagnóstico usa el [[hhi|HHI]] de todas las partidas de la gestión.',
    ],
    'rankings.tabla' => [
        'colegio' => 'Una lista de los primeros puestos (productos, países o departamentos) ordenada de mayor a menor. La columna "acumulado" va sumando qué parte del total explican.',
        'universidad' => 'Tabla de [[pareto|Pareto]] multi-año: top N, resto y total por gestión; la referencia es el año más reciente con datos del [[flujo|flujo]] elegido. Países homologados por [[iso|ISO]].',
    ],
    'indicadores.resumen' => [
        'colegio' => 'Números que resumen el comercio: si Bolivia vendió más de lo que compró ([[tasa-cobertura|tasa de cobertura]]), cuánto vale cada tonelada ([[valor-unitario|valor unitario]]) y si depende de pocos productos ([[hhi|HHI]]).',
        'universidad' => '[[tasa-cobertura|TC]] = X/M×100, [[saldo|saldo]] X−M, [[valor-unitario|VU]] = FOB/t y [[hhi|HHI]] (Σ s²) por producto, país y departamento. La apertura requiere PIB (no cargado).',
    ],
    'linea.serie' => [
        'colegio' => 'Cada barra es un año. Verde es lo que se vendió y azul lo que se compró. Donde falta la barra azul es porque las importaciones de ese año aún no están cargadas.',
        'universidad' => 'Serie anual 1992–2026p de exportación [[fob|FOB]] e importación [[cif|CIF]]; el [[saldo|saldo]] solo se dibuja en años con ambos flujos. Las variaciones se calculan solo entre años completos.',
    ],
    'vias.tablero' => [
        'colegio' => 'Muestra por dónde sale y entra la mercadería: camión o tren, avión, ducto o barco (pasando por un país vecino, porque Bolivia no tiene mar).',
        'universidad' => 'Medios del INE agrupados en cuatro [[via|vías]]. El código [[intermodal|marítimo-intermodal]] solo existe hasta {via_hasta:maritimo}, lo que afecta comparaciones posteriores.',
    ],
    'mapa.flujos' => [
        'colegio' => 'Cada país pintado es un [[socio|socio comercial]]. Verde: Bolivia le vende más de lo que le compra; rojo: le compra más.',
        'universidad' => 'Mapa por [[iso|ISO]] con exportación e importación por país; en gestiones sin importaciones cargadas el saldo bilateral no es computable.',
    ],
];

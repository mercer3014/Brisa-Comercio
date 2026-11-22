<?php

/**
 * Retos y preguntas guiadas (mejora 2). Se siembran en edu_reto con
 * EducacionSeeder. La respuesta correcta NO está aquí: 'consulta' describe qué
 * consulta corre EvaluadorReto contra brisa (las mismas vistas del portal).
 *
 * Tipos de consulta (ver EvaluadorReto):
 *   top_pais, top_departamento, top_via, top_seccion  -> opción
 *   variacion (porcentaje entre dos gestiones), tasa_cobertura -> número
 *   signo_saldo -> binaria (superávit / déficit)
 *   anio_maximo -> opción (años)
 */
return [
    [
        'slug' => 'socio-2010',
        'titulo' => 'El mejor cliente de 2010',
        'enunciado' => '¿A qué país le vendió más Bolivia en 2010?',
        'pista_colegio' => 'Abre Rankings, elige "Exportaciones FOB" y la dimensión "Países (ISO)", con el año 2010. El país del puesto 1 es la respuesta.',
        'pista_universidad' => 'Rankings → flujo exportación, dimensión país, gestión 2010 (referencia). Primer puesto por valor FOB.',
        'pista_ruta' => '/rankings',
        'pista_filtros' => ['gestion' => 2010, 'flujo' => 'exp', 'dimension' => 'pais'],
        'tipo_respuesta' => 'opcion',
        'consulta' => ['tipo' => 'top_pais', 'gestion' => 2010, 'flujo' => 'exp'],
        'puntos' => 10, 'nivel' => 'ambos', 'insignia' => 'socio-comercial', 'orden' => 1,
    ],
    [
        'slug' => 'caida-2015',
        'titulo' => 'Después del boom',
        'enunciado' => 'Compara las exportaciones de 2014 y 2015. ¿En qué porcentaje cambiaron? (escribe un número; si cayeron, ponlo negativo)',
        'pista_colegio' => 'En Línea de tiempo haz clic en 2014 y en 2015 y anota las exportaciones. Calcula (2015 − 2014) ÷ 2014 × 100. Se acepta una diferencia de 2 puntos.',
        'pista_universidad' => 'Variación interanual = (X2015 − X2014) / X2014 × 100. Tolerancia: 1 punto porcentual.',
        'pista_ruta' => '/linea-de-tiempo',
        'pista_filtros' => ['gestion' => 2015],
        'tipo_respuesta' => 'numero',
        'consulta' => ['tipo' => 'variacion', 'desde' => 2014, 'hasta' => 2015, 'flujo' => 'exp', 'tolerancia' => 1],
        'puntos' => 15, 'nivel' => 'ambos', 'insignia' => null, 'orden' => 2,
    ],
    [
        'slug' => 'balanza-1999',
        'titulo' => 'La balanza de 1999',
        'enunciado' => 'En 1999, ¿la balanza comercial de Bolivia fue superavitaria o deficitaria?',
        'pista_colegio' => 'Abre Indicadores en la gestión 1999 y mira el cuadro "Saldo comercial": si es negativo, hubo déficit.',
        'pista_universidad' => 'Saldo = X FOB − M CIF frontera de 1999 (/indicadores, gestión 1999).',
        'pista_ruta' => '/indicadores',
        'pista_filtros' => ['gestion' => 1999],
        'tipo_respuesta' => 'binaria',
        'consulta' => ['tipo' => 'signo_saldo', 'gestion' => 1999],
        'puntos' => 10, 'nivel' => 'ambos', 'insignia' => 'balanza', 'orden' => 3,
    ],
    [
        'slug' => 'via-2022',
        'titulo' => '¿Por dónde salió el dinero?',
        'enunciado' => '¿Cuál fue la vía de transporte con más valor exportado en 2022?',
        'pista_colegio' => 'Abre Comercio por vía, elige la gestión 2022 y compara las exportaciones de cada vía.',
        'pista_universidad' => 'Comercio por vía, gestión 2022: compara el valor FOB por vía (terrestre, marítima intermodal, aérea, otros).',
        'pista_ruta' => '/comercio-por-via',
        'pista_filtros' => ['gestion' => 2022],
        'tipo_respuesta' => 'opcion',
        'consulta' => ['tipo' => 'top_via', 'gestion' => 2022, 'flujo' => 'exp'],
        'puntos' => 10, 'nivel' => 'ambos', 'insignia' => null, 'orden' => 4,
    ],
    [
        'slug' => 'departamento-2025',
        'titulo' => 'El departamento que más exporta',
        'enunciado' => '¿Qué departamento de Bolivia exportó más en 2025?',
        'pista_colegio' => 'En Rankings elige "Departamentos" y el año 2025. Mira el primer puesto.',
        'pista_universidad' => 'Rankings → dimensión departamento, flujo exportación, gestión 2025.',
        'pista_ruta' => '/rankings',
        'pista_filtros' => ['gestion' => 2025, 'flujo' => 'exp', 'dimension' => 'departamento'],
        'tipo_respuesta' => 'opcion',
        'consulta' => ['tipo' => 'top_departamento', 'gestion' => 2025, 'flujo' => 'exp'],
        'puntos' => 10, 'nivel' => 'ambos', 'insignia' => null, 'orden' => 5,
    ],
    [
        'slug' => 'anio-record',
        'titulo' => 'El año récord',
        'enunciado' => 'Entre 1992 y 2025, ¿en qué gestión Bolivia exportó más?',
        'pista_colegio' => 'En Línea de tiempo busca la barra verde más alta.',
        'pista_universidad' => 'Serie anual de exportaciones FOB (solo años completos): máximo de la serie.',
        'pista_ruta' => '/linea-de-tiempo',
        'pista_filtros' => [],
        'tipo_respuesta' => 'opcion',
        'consulta' => ['tipo' => 'anio_maximo', 'flujo' => 'exp', 'desde' => 1992, 'hasta' => 2025],
        'puntos' => 10, 'nivel' => 'ambos', 'insignia' => null, 'orden' => 6,
    ],
    [
        'slug' => 'origen-1999',
        'titulo' => '¿De dónde compramos?',
        'enunciado' => '¿De qué país compró más Bolivia en 1999?',
        'pista_colegio' => 'En Rankings elige "Importaciones CIF", "Países (ISO)" y el año 1999.',
        'pista_universidad' => 'Rankings → flujo importación, dimensión país, gestión 1999.',
        'pista_ruta' => '/rankings',
        'pista_filtros' => ['gestion' => 1999, 'flujo' => 'imp', 'dimension' => 'pais'],
        'tipo_respuesta' => 'opcion',
        'consulta' => ['tipo' => 'top_pais', 'gestion' => 1999, 'flujo' => 'imp'],
        'puntos' => 10, 'nivel' => 'ambos', 'insignia' => 'socio-comercial', 'orden' => 7,
    ],
    [
        'slug' => 'seccion-2025',
        'titulo' => 'La familia de productos líder',
        'enunciado' => '¿Qué sección del Sistema Armonizado concentró más exportaciones en 2025?',
        'pista_colegio' => 'En Rankings elige "Secciones del SA" y el año 2025.',
        'pista_universidad' => 'Rankings → dimensión sección SA, flujo exportación, gestión 2025.',
        'pista_ruta' => '/rankings',
        'pista_filtros' => ['gestion' => 2025, 'flujo' => 'exp', 'dimension' => 'seccion'],
        'tipo_respuesta' => 'opcion',
        'consulta' => ['tipo' => 'top_seccion', 'gestion' => 2025, 'flujo' => 'exp'],
        'puntos' => 15, 'nivel' => 'ambos', 'insignia' => null, 'orden' => 8,
    ],
    [
        'slug' => 'cobertura-1995',
        'titulo' => 'Tasa de cobertura 1995',
        'enunciado' => '¿Cuál fue la tasa de cobertura (exportaciones ÷ importaciones × 100) de Bolivia en 1995? Escribe el porcentaje.',
        'pista_colegio' => 'En Indicadores, gestión 1995, mira el primer cuadro. Se acepta una diferencia de 4 puntos.',
        'pista_universidad' => 'TC = X FOB / M CIF frontera × 100 (gestión 1995). Tolerancia: 2 puntos.',
        'pista_ruta' => '/indicadores',
        'pista_filtros' => ['gestion' => 1995],
        'tipo_respuesta' => 'numero',
        'consulta' => ['tipo' => 'tasa_cobertura', 'gestion' => 1995, 'tolerancia' => 2],
        'puntos' => 20, 'nivel' => 'universidad', 'insignia' => 'balanza', 'orden' => 9,
    ],
];

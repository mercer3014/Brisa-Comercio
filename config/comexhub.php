<?php

/*
|--------------------------------------------------------------------------
| Configuracion de dominio de Geodata
|--------------------------------------------------------------------------
| Campos canonicos del microdato (destino del mapeo de columnas). Cada
| columna de un archivo se traduce a uno de estos campos por NOMBRE.
| La lista proviene del RESUMEN DEL ESQUEMA (estructura-db.sql).
*/

return [

    // Campo canonico => [etiqueta, grupo, tipo]
    'campos_canonicos' => [
        // Tiempo
        'gestion'                => ['etiqueta' => 'Gestion (anio)',        'grupo' => 'Tiempo',          'tipo' => 'entero'],
        'mes'                    => ['etiqueta' => 'Mes',                   'grupo' => 'Tiempo',          'tipo' => 'entero'],
        // Operacion
        'tipo_operacion'         => ['etiqueta' => 'Tipo de operacion',     'grupo' => 'Operacion',       'tipo' => 'texto'],
        'flujo'                  => ['etiqueta' => 'Flujo comercial',       'grupo' => 'Operacion',       'tipo' => 'texto'],
        // Producto / clasificaciones
        'codigo_nandina'         => ['etiqueta' => 'Codigo NANDINA',        'grupo' => 'Producto',        'tipo' => 'entero'],
        'descripcion_producto'   => ['etiqueta' => 'Descripcion producto',  'grupo' => 'Producto',        'tipo' => 'texto'],
        'codigo_capitulo'        => ['etiqueta' => 'Codigo capitulo',       'grupo' => 'Producto',        'tipo' => 'entero'],
        'codigo_seccion'         => ['etiqueta' => 'Codigo seccion',        'grupo' => 'Producto',        'tipo' => 'entero'],
        'codigo_cuci'            => ['etiqueta' => 'Codigo CUCI',           'grupo' => 'Clasificacion',   'tipo' => 'texto'],
        'codigo_gce'             => ['etiqueta' => 'Codigo GCE',            'grupo' => 'Clasificacion',   'tipo' => 'texto'],
        'codigo_ciiu'            => ['etiqueta' => 'Codigo CIIU',           'grupo' => 'Clasificacion',   'tipo' => 'texto'],
        'codigo_grupo_actividad' => ['etiqueta' => 'Codigo grupo actividad','grupo' => 'Clasificacion',   'tipo' => 'texto'],
        'codigo_tnt'             => ['etiqueta' => 'Codigo TNT',            'grupo' => 'Clasificacion',   'tipo' => 'entero'],
        'codigo_cuode'           => ['etiqueta' => 'Codigo CUODE',          'grupo' => 'Clasificacion',   'tipo' => 'texto'],
        // Geografia y logistica
        'codigo_pais'            => ['etiqueta' => 'Codigo pais',           'grupo' => 'Geografia',       'tipo' => 'entero'],
        'codigo_zona'            => ['etiqueta' => 'Codigo zona',           'grupo' => 'Geografia',       'tipo' => 'entero'],
        'codigo_departamento'    => ['etiqueta' => 'Codigo departamento',   'grupo' => 'Geografia',       'tipo' => 'entero'],
        'codigo_medio'           => ['etiqueta' => 'Codigo medio transporte','grupo' => 'Logistica',      'tipo' => 'entero'],
        'codigo_via'             => ['etiqueta' => 'Codigo via',            'grupo' => 'Logistica',       'tipo' => 'entero'],
        'codigo_aduana'          => ['etiqueta' => 'Codigo aduana',         'grupo' => 'Logistica',       'tipo' => 'entero'],
        // Metricas de peso
        'peso_bruto_kg'          => ['etiqueta' => 'Peso bruto (kg)',       'grupo' => 'Metricas peso',   'tipo' => 'decimal'],
        'peso_neto_kg'           => ['etiqueta' => 'Peso neto (kg)',        'grupo' => 'Metricas peso',   'tipo' => 'decimal'],
        'peso_fino_kg'           => ['etiqueta' => 'Peso fino (kg)',        'grupo' => 'Metricas peso',   'tipo' => 'decimal'],
        // Metricas de valor
        'valor_fob_usd'          => ['etiqueta' => 'Valor FOB (USD)',       'grupo' => 'Metricas valor',  'tipo' => 'decimal'],
        'valor_cif_frontera_usd' => ['etiqueta' => 'Valor CIF frontera (USD)','grupo' => 'Metricas valor','tipo' => 'decimal'],
        'valor_cif_aduana_usd'   => ['etiqueta' => 'Valor CIF aduana (USD)','grupo' => 'Metricas valor',  'tipo' => 'decimal'],
        'gravamenes_pagados'     => ['etiqueta' => 'Gravamenes pagados',    'grupo' => 'Metricas valor',  'tipo' => 'decimal'],
    ],

    /*
    | Alias conocidos: nombre de columna de origen (normalizado en MAYUSCULAS,
    | sin espacios) => campo canonico. Sirven de sugerencia automatica al
    | construir un perfil o al detectar columnas desconocidas.
    */
    'alias_columnas' => [
        // Tiempo
        'GESTION' => 'gestion', 'ANIO' => 'gestion', 'ANO' => 'gestion', 'YEAR' => 'gestion',
        'MES' => 'mes', 'MONTH' => 'mes',
        // Operacion / flujo
        'TIPOOPERACION' => 'tipo_operacion', 'TIPO_OPERACION' => 'tipo_operacion',
        'FLUJO' => 'flujo', 'FLUJOCOMERCIAL' => 'flujo',
        // Producto
        'NANDINA' => 'codigo_nandina', 'PARTIDA' => 'codigo_nandina', 'CODIGONANDINA' => 'codigo_nandina',
        'DESCRIPCION' => 'descripcion_producto', 'DESCRIPCIONPRODUCTO' => 'descripcion_producto', 'GLOSA' => 'descripcion_producto',
        'CAPITULO' => 'codigo_capitulo', 'SECCION' => 'codigo_seccion',
        // Clasificaciones (incluye alias documentados)
        'CUCI' => 'codigo_cuci', 'CUCI3' => 'codigo_cuci', 'CUCIR3' => 'codigo_cuci',
        'GCE' => 'codigo_gce', 'CIIU' => 'codigo_ciiu', 'GRUPOACTIVIDAD' => 'codigo_grupo_actividad',
        'TNT' => 'codigo_tnt', 'CUODE' => 'codigo_cuode',
        // Geografia
        'PAIS' => 'codigo_pais', 'CODPAIS' => 'codigo_pais', 'ZONA' => 'codigo_zona',
        'DEPTO' => 'codigo_departamento', 'DEPARTAMENTO' => 'codigo_departamento',
        // Logistica
        'MEDIO' => 'codigo_medio', 'MEDIOTRANSPORTE' => 'codigo_medio', 'TRANSPORTE' => 'codigo_medio',
        'VIA' => 'codigo_via', 'ADUANA' => 'codigo_aduana', 'CODADUANA' => 'codigo_aduana',
        // Pesos (incluye alias documentados)
        'KILBRU' => 'peso_bruto_kg', 'KILOS' => 'peso_bruto_kg', 'PESOBRUTO' => 'peso_bruto_kg', 'KILOBRUTO' => 'peso_bruto_kg',
        'KILNET' => 'peso_neto_kg', 'PESONETO' => 'peso_neto_kg', 'KILONETO' => 'peso_neto_kg',
        'PFINO' => 'peso_fino_kg', 'FINO' => 'peso_fino_kg', 'PESOFINO' => 'peso_fino_kg',
        // Valores
        'FOB' => 'valor_fob_usd', 'VALORFOB' => 'valor_fob_usd', 'FOBUSD' => 'valor_fob_usd',
        'CIF' => 'valor_cif_frontera_usd', 'CIFFRONTERA' => 'valor_cif_frontera_usd', 'VALORCIF' => 'valor_cif_frontera_usd',
        'CIFADUANA' => 'valor_cif_aduana_usd',
        'GRAVAMEN' => 'gravamenes_pagados', 'GRAVAMENES' => 'gravamenes_pagados',
    ],
];

<?php

/** Insignias del panel Educación (mejora 2). La regla se aplica en Participantes::evaluarInsignias(). */
return [
    ['codigo' => 'primer-acierto', 'titulo' => 'Primer acierto', 'icono' => '★', 'descripcion' => 'Respondiste bien tu primer reto.', 'regla' => '1 reto correcto'],
    ['codigo' => 'explorador', 'titulo' => 'Explorador', 'icono' => '🧭', 'descripcion' => 'Usaste el enlace "Abrir ya filtrado" del portal antes de acertar un reto.', 'regla' => 'acierto con exploración'],
    ['codigo' => 'socio-comercial', 'titulo' => 'Socio comercial', 'icono' => '🌎', 'descripcion' => 'Acertaste un reto de países socios.', 'regla' => 'acierto en reto de país'],
    ['codigo' => 'balanza', 'titulo' => 'Balanza', 'icono' => '⚖', 'descripcion' => 'Acertaste un reto de saldo o tasa de cobertura.', 'regla' => 'acierto en reto de balanza'],
    ['codigo' => 'cinco-aciertos', 'titulo' => 'Analista en formación', 'icono' => '📊', 'descripcion' => 'Resolviste cinco retos distintos.', 'regla' => '5 retos correctos'],
    ['codigo' => 'lector', 'titulo' => 'Lector de datos', 'icono' => '📘', 'descripcion' => 'Completaste tres lecciones de la ruta.', 'regla' => '3 lecciones completadas'],
];

# ComexHub — Pendientes

## Requiere al equipo
- **Lista de errores del panel (Tarea 16)**: el Scrum Master debe entregar el detalle de los errores
  detectados en el panel (cargas, validacion o visualizacion) para reproducir, corregir y documentar
  cada uno. El drag&drop y el endurecimiento de contadores ya estan hechos; falta esa lista.
- **Cabeceras reales del INE** (exportacion e importacion, todas las gestiones): los
  perfiles base INE-EXPO-base / INE-IMPO-base ya existen con un mapeo provisional usando
  los alias conocidos. Validar y completar contra archivos reales (editor en /admin/perfiles).
- **Archivo(s) Excel/CSV reales del INE** para probar el ETL y validar indicadores con datos
  reales (hoy probado con dataset sintetico de 580 hechos, 2022-2024).

## Mejoras diferidas (segunda etapa)
- **Exportacion a nivel MICRODATO**: el explorador publico (Tarea 15) ya exporta el detalle filtrado
  en streaming (OpenSpout + cursor), sincrono. Para volumenes muy grandes convendria moverlo a un Job
  en cola con aviso al terminar (el patron ProcesarCargaArchivo se reutilizaria).
- **Forzar cambio de contrasenia** cuando `usuario.debe_cambiar_pwd = true` (redirigir a una
  pantalla de cambio en el primer ingreso).
- **Catalogos**: ampliar la edicion a mas dimensiones (CIIU, GCE, TNT, CUODE, despachante) y
  permitir alta/baja, no solo edicion.
- **Equivalencias de codigos de pais entre organizaciones**: la tabla llegara cuando entre la
  segunda organizacion (ALADI/MERCOSUR/FAOSTAT); hoy solo INE (forma microdato).
- **Nombres de dimensiones**: el ETL crea placeholders ("Pais 105") cuando el archivo solo
  trae el codigo. Con catalogos oficiales del INE se completarian los nombres reales.
- **Rendimiento del facetado** con 400k filas: medir y, si hace falta, materializar vistas
  agregadas o cachear conteos de facetas.
- **Tests automatizados**: phpunit.xml usa sqlite en memoria (sin el esquema PostgreSQL real);
  configurar una suite que apunte a una BD PostgreSQL de pruebas para feature tests.

## Notas tecnicas
- PostgreSQL 18.2, base `brisa`. Credenciales en `.env` (NO commitear la contrasena real).
- XAMPP PHP 8.2: se habilitaron `pdo_pgsql` y `pgsql` en `php.ini` (cambio fuera del repo).
- Proyecto es **Laravel 12** (el plan decia 11; compatible).
- Para procesar cargas en produccion: ejecutar un worker de cola
  (`php artisan queue:work`) o usar el script `composer dev` que ya lo incluye.
- Librerias clave: inertiajs/inertia-laravel, openspout/openspout (ETL/exportacion),
  barryvdh/laravel-dompdf (PDF), apexcharts + vue3-apexcharts (graficos).

## Estado: Tareas 1-16 + tarea02 (reproducibilidad + redisenio) COMPLETADAS
- Admin inicial: usuario **admin** / **Admin12345** (configurable via ADMIN_* en `.env`).
- Arranque reproducible desde cero: `php artisan migrate --seed` (crea las 39 tablas +
  vistas y siembra datos base), luego `composer dev` (o `serve` + `npm run dev` + `queue:work`).
  Ver `README.md`. Las migraciones del esquema estan en `2026_06_05_*`.
- Identidad visual institucional (azul #193153 / rojo #e10f1c, Inter+Fraunces) en
  `resources/css/app.css` (@theme, Tailwind v4); guia en `memoria/sistema-disenio.md`.

## Pendiente visual (placeholders a reemplazar por el equipo)
- Escudo de la FCEE y logos institucionales: hoy son cajas con borde punteado en el header,
  footer y login. Reemplazar por las imagenes reales.
- Imagenes de fondo del hero: hoy se usa color + trama sutil; se pueden sustituir por foto.
- "Ultima actualizacion" del header/footer: hoy muestra la fecha actual del navegador;
  conectar a la fecha real de la ultima carga si se desea.

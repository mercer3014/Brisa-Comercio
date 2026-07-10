# Geodata — Contexto del proyecto

## Que es
Geodata es una plataforma web que **centraliza datos de comercio exterior**
(exportaciones e importaciones) provenientes de varias organizaciones estadisticas.
Se arranca con una sola organizacion: el **INE de Bolivia**.

## Objetivos
1. Cargar archivos Excel/CSV grandes (hasta ~400.000 filas por gestion).
2. Almacenarlos en una base PostgreSQL **ya creada** (el agente no la crea).
3. Mostrarlos en un portal web con filtros dinamicos, busquedas, graficos e
   indicadores, en estilo de portal de datos profesional (limpio, paleta azul,
   tarjetas, tablas y charts).

## Arquitectura de datos (idea central)
- **Una sola base** para todas las organizaciones. Cada fila de hechos lleva
  `organizacion_id`. Para ver solo INE: `WHERE organizacion_id = 1`.
- Las tablas de hechos se separan por **FORMA del dato**, no por organizacion.
  Hoy existe solo la forma "microdato" en `operacion_comercio_exterior` (INE).
- Las cabeceras de los archivos **cambian con los anios** (mismos datos, distintos
  nombres de columna). El mapeo de columnas es por **NOMBRE**, mediante las tablas
  `perfil_mapeo` y `mapeo_columna`. El orden fisico de las columnas no importa.

## Stack tecnologico
- Backend: **Laravel 12** (el documento decia 11; el proyecto venia con 12), PHP 8.2.
- Frontend: **Inertia.js + Vue 3** (Composition API, `<script setup>`) + **Tailwind CSS v4**, sobre **Vite 7**.
- Base de datos: **PostgreSQL 18** (gestionada por el equipo con pgAdmin). Base: `brisa`.
- Cola de trabajos: driver **database** (cargas pesadas en segundo plano).
- Idioma: **todo en espaniol** (codigo, comentarios e interfaz).

## Reglas permanentes (resumen)
1. Mantener la carpeta `memoria/` y leerla al iniciar cada tarea, actualizarla al terminar.
2. La base ya existe; **no** crear ni modificar tablas del negocio, solo modelos Eloquent.
   Las tablas internas de Laravel (jobs, cache, sessions) si se crean por migracion.
3. No avanzar de tarea sin cumplir criterios de aceptacion; reportar resumen.
4. No inventar nombres de tablas/columnas: respetar el esquema exacto (`estructura-db.sql`).
5. Contrasenias solo como **hash bcrypt**, nunca en texto plano.

## Dos caras del sistema (desde Tarea 11)
Geodata tiene un **portal publico** (entrada del sitio, sin login) y un **panel privado**
(detras del login, bajo `/admin`).

### Rutas publicas (sin autenticacion — LayoutPublico)
- `/` → `portal.inicio` (portada con titulares e indicadores; Tarea 12).
- `/explorar` → `portal.explorar` (explorador publico; Tarea 15).
- `/rankings` → `portal.rankings` (rankings y comparadores; Tarea 13).
- `/acerca` → `portal.acerca` (informativa).
- Todas servidas por `PortalController` (comparten `opcionesBase()`: organizaciones, gestiones,
  organizacion por defecto INE). El visitante elige organizacion/anio; cada bloque indica fuente y periodo.

### Acceso (guest)
- `/acceder` (GET/POST) → login (`login` / `login.intento`). Antes era `/login`.

### Rutas privadas (auth + prefijo `/admin` — LayoutAdmin)
- `/admin` → `admin.inicio` (portada del panel). `/logout` (auth, fuera del prefijo).
- Resto bajo `/admin/<modulo>`: usuarios, roles, organizaciones, perfiles, cargas, explorador,
  dashboards, reportes, calidad, bitacora, configuracion, catalogos. Cada accion con `permiso:*`.
- Los **nombres** de ruta se conservan (usuarios.index, explorador.consultar, etc.); solo cambio el path.

### Layouts Vue (`resources/js/Layouts/`)
- **LayoutPublico.vue**: barra con logo + nav del portal + boton "Acceder" (o "Ir al panel" con sesion),
  responsive (menu movil), pie con la fuente.
- **LayoutAdmin.vue**: menu lateral del panel (era `AppLayout.vue`, ahora eliminado).
- `app.js` asigna el layout por convencion: paginas en `Pages/Portal/*` → LayoutPublico; resto → LayoutAdmin.
  El login usa `layout: null`. Tras login → `/admin`; tras logout → `/` (portal).

## Referencia del esquema
El archivo `estructura-db.sql` en la raiz es la referencia exacta de las 39 tablas.
Ver `estructura-bd.md` para el resumen tabla -> modelo -> PK.

# CAMBIOS_EDUCACION.md — Panel “Educación” en el portal público (track de educación)

Fecha: 22/11/2026. Solo cambios locales en la rama `geodata-mejoras`: **sin commit y sin push**. Una migración nueva y aditiva (solo crea tablas `edu_*`), sin borrar ni truncar datos y sin `migrate:fresh`. **No se agregó ningún asistente de IA**: el glosario, las explicaciones y las pistas son contenido estático.

> “Los estudiantes bolivianos aprenden economía con datos reales de su propio país, no con ejemplos inventados del libro.”

## Objetivo
Un panel extra del portal (`/aprende`, menú **Educación**) con las 6 mejoras del track de educación. **Ninguna cifra está escrita a mano**: los textos llevan marcadores (`{exp:2025}`, `{var_exp:2015}`, `{soya:2013}`…) que se reemplazan en vivo con las mismas consultas del portal (`PortalVistas`, vistas `resumen_*`), y las respuestas de los retos se calculan al corregir.

### Cobertura de datos (hoy)
- Exportaciones INE: 1992–2026p. Importaciones INE: solo 1992–1999.
- Por eso la balanza, la tasa de cobertura y los retos de importación usan 1992–1999. Si una gestión no tiene los dos flujos, el reto o la pregunta aparece como **“no disponible”** y los gráficos muestran **“Sin datos”** (barras punteadas), nunca un 0.
- MERCOSUR (`serie_comercio_zona`) tiene 0 filas: la historia “Bolivia en el MERCOSUR” aparece como “Sin datos cargados”.
- Cuando se carguen más años, lecciones, retos e historias se recalculan solos (cache por versión de carga).

## Las 6 mejoras
| # | Mejora | Dónde | Qué hace |
|---|---|---|---|
| 1 | Ruta “Aprende comercio exterior” | `/aprende`, `/aprende/{lección}` | 6 lecciones cortas: exportar e importar, FOB y CIF, balanza comercial, socios comerciales, vías de transporte y qué vende Bolivia. Cada una tiene texto, un gráfico real (algunas con selector de gestión), una pregunta para pensar, enlaces al portal y un botón “Marcar como completada”. |
| 2 | Retos y preguntas guiadas | `/aprende/retos`, `/aprende/retos/{reto}` | 9 retos (socio #1 en 2010, caída 2014→2015, balanza 1999, vía 2022, departamento 2025, año récord, origen de importaciones 1999, sección SA 2025, tasa de cobertura 1995). Cada uno dice a qué página ir y con qué filtros, y tiene el botón **“Abrir ya filtrado”**. La comprobación se hace en el servidor (`EvaluadorReto`) y la respuesta nunca viaja al navegador antes de responder. Hay puntos, 6 insignias y un top 10 por apodo. Los puntos se dan solo en el primer acierto y si la respuesta no se reveló antes (se revela tras 2 fallos). |
| 3 | “¿Qué estoy viendo?” y glosario emergente | Recuadro en Inicio, Productos, Rankings, Indicadores, Línea de tiempo, Comercio por vía y Mapa; `/aprende/glosario` | 28 términos (FOB, CIF, NANDINA, HHI, ISO, M49, tasa de cobertura, gestión preliminar…). En lecciones e historias, los términos subrayados abren un globo con la definición, un ejemplo con datos reales y un enlace. |
| 4 | Modo docente | Admin: `/admin/actividades`; alumno: `/actividad/{código}` o el cuadro de código en `/aprende` | El docente crea una actividad (consigna, enlace al portal ya filtrado, preguntas “con datos” corregidas en vivo, de opción múltiple o abiertas, y fecha de cierre opcional). El sistema genera un código de 6 caracteres. Los alumnos responden solo con un alias. El docente ve el % de aciertos por pregunta, las respuestas abiertas, la matriz por alumno y la descarga CSV, y puede cerrar o reabrir la actividad. |
| 5 | Historias con datos | `/historias`, `/historias/{historia}` | Recorridos paso a paso con gráfico por paso: “El boom exportador” (6 pasos), “La soya boliviana” (4), “La balanza de los 90” (3) y “Bolivia en el MERCOSUR” (sin datos). Se navegan con botones o con las flechas ← →. |
| 6 | Niveles colegio / universidad | Selector “Nivel” en todo el panel (se recuerda en el navegador; `?nivel=universidad` lo fija) | Colegio: lenguaje simple, cifras redondeadas (“9,7 mil millones de dólares”), pistas siempre visibles y tolerancia doble en los retos numéricos. Universidad: términos técnicos, “X M USD”, NANDINA, HHI, CIF frontera y tolerancia normal. |

## Base de datos
Migración `database/migrations/2026_11_22_200001_crear_tablas_educacion.php` (aditiva, `CREATE TABLE IF NOT EXISTS`):

| Tabla | Uso |
|---|---|
| `edu_participante` | Estudiante anónimo (apodo y nivel) ligado a la sesión. Sin correo ni nombre real. |
| `edu_progreso_leccion` | Lecciones completadas. |
| `edu_reto` | Retos: enunciado, pistas por nivel, ruta y filtros del enlace, **definición de la consulta** (`consulta_clave`, sin la respuesta), puntos e insignia. |
| `edu_intento_reto` | Intentos (respuesta, correcta, puntos, nivel, si abrió el enlace). |
| `edu_insignia`, `edu_participante_insignia` | Insignias y cuáles ganó cada estudiante. |
| `edu_actividad` | Actividad docente (docente, código, consigna, nivel, enlace en `configuracion`, activa, cierre). |
| `edu_actividad_pregunta` | Preguntas (`dato` / `opcion` / `abierta`). Las de tipo `dato` guardan solo la consulta. |
| `edu_actividad_participante`, `edu_actividad_respuesta` | Alias de los alumnos y sus respuestas. |

Seeder `database/seeders/EducacionSeeder.php` (idempotente, también llamado desde `DatabaseSeeder`):
- Carga los retos y las insignias.
- Crea los permisos `actividad.ver` y `actividad.crear` (módulo `educacion`) y se los da a `administrador`.
- Crea el rol **docente** con esos dos permisos. `RolPermisoSeeder` también lista el módulo.

## Backend
| Archivo | Cambio |
|---|---|
| `app/Servicios/Educacion/DatosEducativos.php` (nuevo) | Cifras en vivo para el panel: serie anual, variaciones, máximos, FOB/CIF, soya (NANDINA 1201/1507/2304), HHI y vías. Reemplaza los marcadores del contenido y arma los gráficos de lecciones e historias. Formato por nivel. |
| `app/Servicios/Educacion/EvaluadorReto.php` (nuevo) | 8 tipos de consulta (top país, departamento, vía o sección; variación %; tasa de cobertura; superávit/déficit; año máximo). Calcula la respuesta y la explicación al vuelo. |
| `app/Servicios/Educacion/ContenidoEducativo.php` (nuevo) | Lee el contenido estático y lo entrega resuelto por nivel. |
| `app/Servicios/Educacion/Participantes.php` (nuevo) | Participante por sesión, puntos, insignias y progreso. |
| `database/data/aprende/*` (nuevo) | Contenido estático: `lecciones.php`, `glosario.php`, `explicaciones.php`, `retos.php`, `insignias.php` e `historias/*.json`. Se edita sin tocar código. |
| `app/Http/Controllers/EducacionController.php` (nuevo) | Páginas `/aprende…` e `/historias…`, y JSON `/portal/v2/aprende`, `/portal/v2/aprende/glosario` y `/portal/v2/aprende/grafico/{id}`. |
| `app/Http/Controllers/ActividadController.php` (nuevo) | Cara del alumno del modo docente. |
| `app/Http/Controllers/ActividadDocenteController.php` (nuevo) | Cara del docente. Un docente ve solo sus actividades y el administrador ve todas. Usa Auditoría (`ACTIVIDAD_CREADA`, cambio de estado). |
| `routes/web.php` | Rutas públicas del panel (`throttle:120`). Envíos de respuestas con límite propio `throttle:60,1,edu-respuestas`. Rutas admin `/admin/actividades*` con `permiso:actividad.ver` y `permiso:actividad.crear`. |

## Frontend
- `resources/js/Pages/Portal/Aprende/` (nuevo): `Indice`, `Leccion`, `Retos`, `Reto`, `Glosario`.
- `resources/js/Pages/Portal/Historias/` (nuevo): `Indice`, `Historia`.
- `resources/js/Pages/Portal/Actividad/Ver.vue` (nuevo).
- `resources/js/Pages/Educacion/Actividades/` (nuevo, panel admin): `Index`, `Nueva`, `Detalle`.
- `resources/js/Components/Gd/` (nuevos): `GdNivel`, `GdGlosario`, `GdTexto`, `GdExplicacion`, `GdSerieBarras`, `GdGraficoEdu`.
- `resources/js/lib/educacion.js` (nuevo): nivel persistente, glosario cacheado y marcas `[[término|texto]]`.
- `resources/css/educacion.css` (nuevo, importado en `app.css`): estilos `edu-*` acotados a `.gd` con la paleta vino/azul marino.
- `LayoutPublico.vue`: ítem **Educación** en el menú (activo también en `/historias` y `/actividad`) y enlace en el pie. `LayoutAdmin.vue`: grupo **Educación → Actividades** (permiso `actividad.ver`).
- Páginas existentes:
  - Se agregó el recuadro “¿Qué estoy viendo?” (plegado) en Inicio, Productos, Rankings, Indicadores, Línea de tiempo, Comercio por vía y Mapa comercial.
  - Rankings, Indicadores, Línea de tiempo y Comercio por vía ahora aceptan filtros por URL (`?gestion=`, `?anios=`, `?flujo=`, `?dimension=`). Así funcionan los enlaces “Abrir ya filtrado”.

## Aplicar en la PC
```
php artisan migrate                       (solo la migración nueva; nunca migrate:fresh)
php artisan db:seed --class=EducacionSeeder
npm run build
php artisan optimize:clear
```

### Usar el modo docente
1. En `/admin/usuarios`, crear el usuario del profesor y asignarle el rol **docente** (o usar un administrador).
2. Entrar a `/admin/actividades` → **Nueva actividad** → guardar. Se muestra el código, por ejemplo `K7M2QX`.
3. Los alumnos abren `http://127.0.0.1:8000/actividad/K7M2QX` o escriben el código en `/aprende`.
4. El docente revisa los resultados en la actividad (pestañas *Resumen por pregunta* y *Respuestas por estudiante*, con descarga CSV).

## Verificación realizada
- `php artisan route:list`: 12 rutas `aprende`, 2 de `historias` y 9 de `actividad` (3 públicas y 6 admin).
- `curl` sobre `:8000`:
  - Las 18 páginas del panel responden 200 con su componente Inertia. Una lección inexistente da 404. `/admin/actividades` sin sesión redirige a `/acceder`.
  - Los 3 JSON responden 200 y un gráfico desconocido da 404. Un POST sin token CSRF da 419.
  - Flujo de reto por POST con cookie XSRF: un fallo da 0 puntos y no revela la respuesta. El acierto da +10 puntos e insignias. La respuesta numérica −31 se aceptó en nivel colegio (valor real −31,5%). Completar una lección funciona. Un apodo inválido da 422.
- Script PHP:
  - Los 9 retos se evalúan con datos reales y cada respuesta calculada se acepta a sí misma.
  - Las 6 lecciones, el glosario y las 4 historias quedan sin marcadores sin resolver.
  - Los 6 gráficos de lecciones devuelven bloques.
- Modo docente probado dentro de una transacción revertida (no quedó nada guardado): crear, ver como alumno, responder, bloquear el segundo envío, ver estadísticas, matriz, CSV, cerrar e índice.
- La página de reto no incluye la respuesta en sus props.

## Pendiente o notas
- Las importaciones 2000–2026 no están cargadas: los retos y preguntas de balanza, cobertura o importación solo funcionan para 1992–1999. Para otras gestiones aparecen como “no disponible”.
- La historia MERCOSUR queda sin datos hasta que se cargue `serie_comercio_zona`.
- En la matriz docente, las respuestas de preguntas “con datos” de tipo lista se muestran con su código (ISO-3, código de vía o de sección) y las de opción múltiple con su número de opción. La marca ✓/✗ sí es exacta.
- La identidad del estudiante es la sesión del navegador: si borra cookies, empieza de cero con otro apodo.
- `php artisan test` (ExampleTest) falla con SQLite. Es un fallo previo, no una regresión.

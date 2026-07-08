# ComexHub

Plataforma web que **centraliza datos de comercio exterior** (exportaciones e
importaciones) de organizaciones estadísticas. Arranca con una sola organización:
el **INE de Bolivia**. Tiene dos caras:

- **Portal público** (sin login): portada con titulares e indicadores, explorador,
  rankings/comparadores y página informativa.
- **Panel privado** (`/admin`, detrás del login): carga de archivos, ETL, explorador
  de microdatos, dashboards, reportes, catálogos, calidad, configuración, bitácora,
  usuarios, roles, organizaciones y perfiles de mapeo.

> Proyecto academico — FCEE, UAGRM. Fuente de datos: INE Bolivia.

## Stack

- **Backend**: Laravel 12 (PHP 8.2+)
- **Frontend**: Inertia.js + Vue 3 (`<script setup>`) + Tailwind CSS v4 sobre Vite 7
- **Base de datos**: PostgreSQL 14+ (probado en 18)
- **Cola de trabajos**: driver `database` (cargas pesadas / ETL en segundo plano)
- **Librerias clave**: openspout/openspout (lectura y exportación en streaming),
  barryvdh/laravel-dompdf (PDF), apexcharts + vue3-apexcharts (gráficos)

---

## Requisitos

- **PHP 8.2 o superior** con las extensiones `pdo_pgsql` y `pgsql` habilitadas
  (en XAMPP, descomentar `extension=pdo_pgsql` y `extension=pgsql` en `php.ini`).
- **Composer 2**
- **Node.js 18+** y **npm**
- **PostgreSQL 14+** corriendo y accesible.

---

## Puesta en marcha desde cero

### 1. Clonar e instalar dependencias

```bash
git clone <url-del-repo> comexhub
cd comexhub
composer install
npm install
```

### 2. Configurar el entorno

```bash
cp .env.example .env          # en Windows: copy .env.example .env
php artisan key:generate
```

Editar `.env` y ajustar la conexion a PostgreSQL y las credenciales del admin:

```env
APP_NAME=ComexHub
APP_URL=http://localhost:8000
APP_LOCALE=es

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=brisa
DB_USERNAME=postgres
DB_PASSWORD=tu_contrasena
DB_SEARCH_PATH=public

# Administrador inicial (lo siembra el seeder)
ADMIN_USUARIO=admin
ADMIN_CORREO=admin@comexhub.local
ADMIN_PASSWORD=Admin12345
```

Crear la base de datos vacia en PostgreSQL (una sola vez), p. ej. con pgAdmin o:

```sql
CREATE DATABASE brisa;
```

### 3. Crear el esquema y los datos base

```bash
php artisan migrate --seed
```

Esto crea las **39 tablas del esquema** (más las internas de Laravel) y siembra los
**datos base**: roles, permisos, usuario administrador, la organización INE con sus
perfiles de mapeo, la configuración del sistema y las reglas de calidad. Las 4 vistas
materializadas del portal se crean y pueblan en la misma corrida.

> Para reconstruir todo desde cero (¡borra los datos!): `php artisan migrate:fresh --seed`.

### 4. Compilar el frontend y levantar el servidor

En desarrollo (todo a la vez: servidor, cola, logs y Vite):

```bash
composer dev
```

O por separado:

```bash
npm run dev            # Vite (frontend en caliente)
php artisan serve      # http://localhost:8000
php artisan queue:work # worker de la cola (procesa las cargas / ETL)
```

Para producción: `npm run build` y servir `php artisan serve` (o un servidor web)
junto con un worker de cola permanente (`php artisan queue:work`).

---

## Usuario administrador inicial

| Campo       | Valor por defecto      |
|-------------|------------------------|
| Usuario     | `admin`                |
| Contrasenia | `Admin12345`           |
| Correo      | `admin@comexhub.local` |

Configurables vía `ADMIN_USUARIO`, `ADMIN_CORREO` y `ADMIN_PASSWORD` en `.env`.
La contrasenia se guarda solo como **hash bcrypt** y el usuario nace con
`debe_cambiar_pwd = true` (debe cambiarla en el primer ingreso). Ingreso por `/acceder`.

---

## La cola (ETL)

Las cargas de archivos Excel/CSV se procesan en segundo plano mediante un Job en la
cola. Es **imprescindible** tener un worker corriendo para que las cargas pasen de
`PENDIENTE` a `COMPLETADO`:

```bash
php artisan queue:work
```

`composer dev` ya levanta un `queue:listen` para desarrollo.

Tras cada ETL exitoso se refrescan automáticamente las vistas materializadas del
portal. También se pueden refrescar a mano:

```bash
php artisan comexhub:refrescar-vistas
```

---

## Estructura de rutas (resumen)

| Cara          | Middleware            | Rutas |
|---------------|-----------------------|-------|
| Portal público| (ninguno)             | `/`, `/explorar`, `/rankings`, `/acerca` |
| Acceso        | guest                 | `/acceder` (GET/POST) |
| Panel privado | auth + prefix `admin` | `/admin` y `/admin/<modulo>...`; `/logout` |

---

## Documentacion del proyecto

La carpeta [`memoria/`](memoria/) contiene el contexto y la bitácora de desarrollo:

- `contexto-proyecto.md` — vision general, arquitectura de datos, reglas.
- `estructura-bd.md` — resumen de las 39 tablas y su mapeo a modelos Eloquent.
- `bitácora-desarrollo.md` — registro cronologico de cada tarea.
- `pendientes.md` — pendientes y mejoras diferidas.
- `sistema-disenio.md` — paleta institucional, tipografias y tokens visuales.

El archivo `estructura-db.sql` (raiz) es la referencia exacta del esquema; las
migraciones de `database/migrations/2026_06_05_*` lo reproducen tal cual.

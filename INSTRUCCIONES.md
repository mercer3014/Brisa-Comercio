# Instrucciones de instalación — Geodata

Sigue estos pasos **en orden** después de descargar el proyecto. Al terminar
verás la aplicación **exactamente igual** que en la máquina original.

> ⚠️ El código que bajas de git NO incluye las dependencias ni el frontend
> compilado (son carpetas muy pesadas que se generan en tu propia máquina).
> Por eso hay que correr estos comandos: ellos regeneran todo eso localmente.

---

## Requisitos previos

Necesitas tener instalado:

- **PHP 8.2 o superior** (viene con XAMPP)
- **Composer** — https://getcomposer.org
- **Node.js 18+** y **npm** — https://nodejs.org
- **PostgreSQL** (la base de datos se llama `brisa`)

---

## Pasos

### 1. Bajar el código

```bash
git pull
```

(o `git clone <url>` si es la primera vez que lo descargas)

### 2. Instalar dependencias de PHP

```bash
composer install
```

### 3. Instalar dependencias de JavaScript

```bash
npm install
```

### 4. Crear tu archivo de configuración `.env`

El `.env` NO viaja por git (tiene claves personales). Copia la plantilla:

```bash
cp .env.example .env
```

En Windows (PowerShell), si `cp` no funciona:

```powershell
Copy-Item .env.example .env
```

Luego abre el `.env` y ajusta tus datos de **PostgreSQL** si son distintos:

```
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=brisa
DB_USERNAME=postgres
DB_PASSWORD=tu_contraseña
```

> Antes de continuar, crea la base de datos vacía `brisa` en PostgreSQL.

### 5. Generar la clave de la aplicación

```bash
php artisan key:generate
```

### 6. Cargar la base de datos

Tienes **dos opciones**, según lo que te hayan dado:

**Opción A — Te pasaron un backup (`.sql`) de la base de datos** (recomendado,
así ves los mismos datos que el proyecto original):

1. Crea la base vacía `brisa` en PostgreSQL.
2. Importa el backup:

   ```bash
   psql -U postgres -d brisa -f nombre_del_backup.sql
   ```

   > NO corras `migrate --seed` en este caso: el backup ya trae todo (tablas y
   > datos) y se pisaría.

**Opción B — Empezar con una base vacía** (sin los datos de comercio):

```bash
php artisan migrate --seed
```

Esto crea todas las tablas y carga solo los datos base (usuario admin, roles,
configuración). Los dashboards saldrán vacíos hasta que se carguen datos.

### 7. ⭐ Compilar el frontend (¡ESTE es el paso clave!)

Este comando es el que hace que veas el nombre y el diseño correctos
("Geodata"). Sin él te quedas con una versión vieja.

**Para solo verlo funcionando:**

```bash
npm run build
```

**Para desarrollar (se recompila solo al guardar cambios):**

```bash
npm run dev
```

### 8. Levantar el servidor

En una terminal:

```bash
php artisan serve
```

Abre el navegador en la dirección que te muestre (normalmente
http://127.0.0.1:8000).

> Si usaste `npm run dev` en el paso 7, déjalo corriendo en **otra** terminal
> al mismo tiempo que `php artisan serve`.

---

## ¿Sigues viendo algo raro / viejo?

Casi siempre es el frontend en caché. Recompila y limpia:

```bash
npm run build
php artisan optimize:clear
```

Luego recarga el navegador con **Ctrl + F5** (recarga forzada).

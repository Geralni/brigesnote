# 🏫 Bridges Note - MINEDU ESCALE Bridge API

Microservicio y API REST en **Laravel 13 (PHP 8.3)** diseñado para sincronizar, estructurar y servir el catálogo oficial de instituciones educativas (colegios) y la división política/geográfica (regiones, provincias y distritos) del Perú, consumiendo directamente la API pública oficial de **MINEDU / ESCALE** (*Estadística de la Calidad Educativa*).

> El proyecto se migró de Laravel 7 / PHP 7.4 a Laravel 13 / PHP 8.3. Las decisiones y etapas están en [docs/PLAN_MIGRACION_PHP83.md](docs/PLAN_MIGRACION_PHP83.md).

---

## 📋 Tabla de Contenidos

1. [Propósito del Proyecto](#-propósito-del-proyecto)
2. [Arquitectura y Flujo de Datos](#-arquitectura-y-flujo-de-datos)
3. [Requisitos del Sistema](#-requisitos-del-sistema)
4. [Instalación y Puesta en Marcha](#-instalación-y-puesta-en-marcha)
5. [Configuración de Entorno (.env)](#-configuración-de-entorno-env)
6. [Sincronización de Datos (Consola Artisan)](#-sincronización-de-datos-consola-artisan)
7. [Documentación de la API REST](#-documentación-de-la-api-rest)
8. [Pruebas y Análisis Estático](#-pruebas-y-análisis-estático)
9. [Estructura del Proyecto](#-estructura-del-proyecto)
10. [Solución de Problemas Frecuentes](#-solución-de-problemas-frecuentes)
11. [Mejoras Futuras](#-mejoras-futuras)

---

## 🎯 Propósito del Proyecto

Servir como un **puente de datos optimizado** para aplicaciones cliente (sistemas de admisión universitaria, plataformas de matrícula escolar, intranets, etc.), permitiéndoles autocompletar ubigeos y consultar colegios mediante consultas locales ultrarrápidas, sin depender de la latencia o disponibilidad de los servidores estatales.

---

## 🔄 Arquitectura y Flujo de Datos

```
[ API MINEDU / ESCALE ]
 (escale.minedu.gob.pe)
          │
          ▼  (Comando: php artisan sync:locations)
[ Base de Datos Local MySQL/MariaDB (escalebridge) ]
  ├── regions
  ├── provinces
  ├── districts
  └── schools (con ubigeo / idDistrito)
          │
          ▼  (LocationController)
[ Endpoints API REST Local / Producción ]
  ├── GET /api/regiones
  ├── GET /api/provincias/{idRegion}
  ├── GET /api/distritos/{idProvince}
  └── GET /api/colegios/{ubigeo}
          │
          ▼
[ Clientes / Aplicaciones Externas ] (ej. admisionweb)
```

---

## 💻 Requisitos del Sistema

- **PHP:** `8.3` o superior (Laravel 13 soporta PHP 8.3 – 8.5).
  Extensiones: `pdo_mysql`, `mbstring`, `curl`, `openssl`, `fileinfo`, `tokenizer`, `xml`, `ctype`.
- **Laravel Framework:** `13.x`
- **Gestor de Paquetes:** Composer 2.x
- **Base de Datos:** MySQL 5.7+ o MariaDB 10.3+ (mínimos que exige Laravel 13).
  Los endpoints usan `LEFT()`, propio de MySQL/MariaDB: **no** se admite SQLite.
- **Servidor Web:** Herd, XAMPP, Laragon o `php artisan serve`. El *document root* debe ser la carpeta **`public/`**.

---

## 🚀 Instalación y Puesta en Marcha

### 1. Clonar el repositorio
```bash
git clone https://github.com/hubelsolis/brigesnote.git
cd brigesnote
```

### 2. Instalar dependencias de Composer
```bash
composer install
```

### 3. Configurar variables de entorno
Copia el archivo de ejemplo y genera la clave de cifrado:
```bash
cp .env.example .env
php artisan key:generate
```

### 4. Crear la Base de Datos
Crea una base de datos vacía en tu gestor MySQL/MariaDB (ej. HeidiSQL o phpMyAdmin):
- **Nombre:** `escalebridge`
- **Cotejamiento:** `utf8mb4_unicode_ci`

### 5. Ejecutar las Migraciones
```bash
php artisan migrate
```

### 6. Sincronizar el catálogo escolar
Descarga la información oficial desde MINEDU:
```bash
php artisan sync:locations
```

### 7. Iniciar el servidor
```bash
php artisan serve
```
La aplicación quedará disponible en `http://127.0.0.1:8000`.

> En Apache/IIS apunta el *document root* a `public/` (allí están `index.php`, `.htaccess` y `web.config`). Nunca sirvas la raíz del proyecto: expondría `.env`, `composer.json` y `storage/logs`.

---

## ⚙️ Configuración de Entorno (.env)

Valores mínimos en `.env` (ver `.env.example`):

```env
APP_NAME="Bridges Note"
APP_ENV=local
APP_KEY=base64:...
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

# Conexión MySQL / MariaDB
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=escalebridge
DB_USERNAME=root
DB_PASSWORD=

# Sesión, caché y colas sin tablas en la BD
SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=sync
```

> Desde Laravel 11 la variable de caché se llama `CACHE_STORE` (antes `CACHE_DRIVER`).

---

## 📥 Sincronización de Datos (Consola Artisan)

El comando central del sistema es:

```bash
php artisan sync:locations
```

### ¿Cómo opera la sincronización?
1. **Regiones (`/padron/rest/regiones`):** descarga los 25 departamentos del país con sus nombres, coordenadas (`pointX`, `pointY`) y nivel de `zoom`.
2. **Provincias (`/padron/rest/provincias?idRegion={id}`):** por cada región, descarga sus provincias.
3. **Distritos (`/padron/rest/distritos?idProvincia={id}`):** por cada provincia, descarga los distritos correspondientes.
4. **Colegios (`/padron/rest/instituciones?estados=1&ubigeo={idDistrito}`):** por cada distrito, descarga las instituciones educativas activas y las guarda en la tabla `schools`, con el código modular (`codMod`) como llave primaria y asociadas a su `idDistrito`.

- Es **idempotente**: usa `updateOrCreate`, así que volver a ejecutarlo actualiza los registros sin duplicarlos.
- Si ESCALE responde con error HTTP en un paso, el comando lo informa en consola y continúa con el resto. El código de salida es siempre `0`.
- Si una respuesta no trae la clave esperada (`region`, `provincia`, `distrito`) o un colegio no trae `cenEdu`, `cenPob` o `dirCen`, ese dato se trata como vacío/`null` en lugar de abortar.

> ⏱️ **Nota:** la sincronización total recorre más de 1,800 distritos y 80,000 colegios, por lo que puede tomar varios minutos según la velocidad de conexión y la respuesta del servidor del MINEDU.

---

## 📡 Documentación de la API REST

Todos los endpoints retornan JSON bajo el prefijo `/api/`, tienen un límite de **60 peticiones por minuto** (`X-RateLimit-Limit: 60`) y CORS abierto (`Access-Control-Allow-Origin: *`).

**Tipos en el JSON** (observado con PHP 8.3 + `pdo_mysql`): las coordenadas `pointX`/`pointY` son columnas `DECIMAL(10,7)` y se devuelven como **string** con 7 decimales (`"-12.0463740"`); `zoom` es `INT` y se devuelve como **número**. Las respuestas incluyen además `created_at` y `updated_at`.

### 1. Listar Regiones / Departamentos

- **URL:** `/api/regiones`
- **Método:** `GET`
- **Respuesta (200 OK):** siempre 200; si no hay datos, `data` es `[]`.
```json
{
  "status": "success",
  "data": [
    {
      "idRegion": "15",
      "nombreRegion": "LIMA",
      "pointX": "-12.0463740",
      "pointY": "-77.0427930",
      "zoom": 10,
      "created_at": "2026-11-16T12:00:00.000000Z",
      "updated_at": "2026-11-16T12:00:00.000000Z"
    }
  ]
}
```

---

### 2. Listar Provincias por Región
Devuelve las provincias cuyo `idProvincia` empieza con los **2 dígitos** del código de región (`LEFT(idProvincia, 2) = {idRegion}`).

- **URL:** `/api/provincias/{idRegion}`
- **Ejemplo:** `/api/provincias/15` (Provincias de Lima)
- **Respuesta (200 OK):**
```json
{
  "status": "success",
  "data": [
    {
      "idProvincia": "1501",
      "nombreProvincia": "LIMA",
      "pointX": "-12.0463740",
      "pointY": "-77.0427930",
      "created_at": "2026-11-16T12:00:00.000000Z",
      "updated_at": "2026-11-16T12:00:00.000000Z"
    }
  ]
}
```
- **Sin resultados (404):**
```json
{ "status": "error", "message": "No se encontraron provincias para esta región." }
```

---

### 3. Listar Distritos por Provincia
Devuelve los distritos cuyo `idDistrito` empieza con los **4 dígitos** del ubigeo provincial (`LEFT(idDistrito, 4) = {idProvince}`).

- **URL:** `/api/distritos/{idProvince}`
- **Ejemplo:** `/api/distritos/1501` (Distritos de la provincia de Lima)
- **Respuesta (200 OK):**
```json
{
  "status": "success",
  "data": [
    { "idDistrito": "150101", "nombreDistrito": "LIMA", "created_at": "...", "updated_at": "..." },
    { "idDistrito": "150102", "nombreDistrito": "ANCON", "created_at": "...", "updated_at": "..." }
  ]
}
```
- **Sin resultados (404):**
```json
{ "status": "error", "message": "No se encontraron distritos para esta provincia." }
```

---

### 4. Listar Colegios por Distrito (Ubigeo)
Devuelve todas las instituciones educativas de un distrito según su ubigeo de 6 dígitos.

- **URL:** `/api/colegios/{ubigeo}`
- **Ejemplo:** `/api/colegios/150101`
- **Respuesta (200 OK):**
```json
{
  "status": "success",
  "data": [
    {
      "codMod": "0273572",
      "idDistrito": "150101",
      "nombreCenEdu": "JUAN JIMENEZ PIMENTEL",
      "cenPob": "LIMA",
      "dirCen": "JR. LAMPA 240",
      "director": "PEDRO PEREZ",
      "dis_crecer": null,
      "dis_juntos": null,
      "created_at": "2026-11-16T12:00:00.000000Z",
      "updated_at": "2026-11-16T12:00:00.000000Z"
    }
  ]
}
```
- **Sin resultados (404):**
```json
{ "status": "error", "message": "No se encontraron escuelas para este distrito." }
```

---

## 🧪 Pruebas y Análisis Estático

Las pruebas corren contra **MySQL/MariaDB** (no SQLite, por el uso de `LEFT()`), en una base dedicada:

```sql
CREATE DATABASE escalebridge_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

`phpunit.xml` fija `DB_DATABASE=escalebridge_test`, y `tests/TestCase.php` aborta si la base configurada es otra, porque `RefreshDatabase` borra las tablas.

```bash
php artisan test                      # PHPUnit 12
vendor/bin/phpstan analyse            # Larastan, nivel 5 (phpstan.neon)
vendor/bin/pint --test                # estilo (solo verificación)
```

- `tests/Feature/Api/LocationApiTest.php`: los 4 endpoints, el formato `{status, data}`, los 404, el throttle y CORS.
- `tests/Feature/SyncLocationsTest.php`: `sync:locations` con `Http::fake()` y `Http::preventStrayRequests()`; **ninguna prueba llama al MINEDU real**.
- `tests/Feature/ExampleTest.php`: la ruta `/` responde 200.

---

## 📂 Estructura del Proyecto

```
brigesnote/
├── app/
│   ├── Console/Commands/
│   │   └── SyncLocations.php       # Comando 'sync:locations' (consumo de API ESCALE)
│   ├── Http/Controllers/
│   │   └── LocationController.php  # Controlador de endpoints /api/
│   └── Models/
│       ├── District.php            # Modelo Distritos
│       ├── Province.php            # Modelo Provincias
│       ├── Region.php              # Modelo Regiones
│       └── School.php              # Modelo Colegios / Escuelas
├── bootstrap/app.php               # Rutas, middleware (throttle:60,1) y excepciones
├── config/cors.php                 # CORS para api/*
├── database/
│   ├── factories/                  # Factories de los modelos (usadas en las pruebas)
│   └── migrations/                 # regions, provinces, districts, schools (+ users)
├── docs/PLAN_MIGRACION_PHP83.md    # Plan y decisiones de la migración
├── public/                         # Document root (index.php, .htaccess, web.config)
├── routes/
│   └── api.php                     # Definición de rutas REST
├── tests/                          # PHPUnit (API y sincronización)
├── .env.example                    # Plantilla de variables de entorno
└── phpstan.neon                    # Configuración de Larastan
```

---

## 🛠️ Solución de Problemas Frecuentes

### 1. Error: `Your requirements could not be resolved` / `php ^8.3`
- **Causa:** Composer se está ejecutando con una versión de PHP anterior a 8.3.
- **Solución:** comprueba `php -v` y selecciona PHP 8.3+ en Herd, Laragon o XAMPP.

### 2. Error: `Base table or view not found: 1146 Table '...sessions' doesn't exist` (o `cache`, `jobs`)
- **Causa:** el `.env` usa `SESSION_DRIVER=database`, `CACHE_STORE=database` o `QUEUE_CONNECTION=database`, y este proyecto no crea esas tablas.
- **Solución:** usa `SESSION_DRIVER=file`, `CACHE_STORE=file` y `QUEUE_CONNECTION=sync`.

### 3. Las pruebas fallan con `Las pruebas deben usar escalebridge_test`
- **Causa:** la base de pruebas no está configurada o hay variables de entorno que la sobrescriben.
- **Solución:** crea `escalebridge_test` y no definas `DB_DATABASE` en el entorno al ejecutar `php artisan test`.

### 4. La web muestra el código o un 404 de Apache
- **Causa:** el *document root* apunta a la raíz del proyecto.
- **Solución:** apúntalo a `public/`.

---

## 🔭 Mejoras Futuras

- **Errores de conexión en `sync:locations`:** un timeout o una caída de red (`Illuminate\Http\Client\ConnectionException`) aborta hoy toda la sincronización. Conviene capturarla por paso, informar y continuar, con reintentos (`Http::retry()`) y un `timeout()` explícito.
- **Estilo:** `vendor/bin/pint --test` aún reporta diferencias de formato en el código original (`SyncLocations`, `LocationController`, modelos); se dejaron sin reformatear durante la migración.
- **MariaDB 10.4** ya no tiene soporte (EOL jun-2024); se recomienda actualizar a una versión vigente.

---

## 📄 Licencia

Este proyecto es de código abierto bajo la licencia MIT.

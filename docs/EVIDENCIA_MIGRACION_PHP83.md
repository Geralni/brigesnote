# Evidencia del proceso: migración de Bridges Note a PHP 8.3

Curso: Arquitectura de Software. Rama: `feat/migracion-php-8.3`. Fecha del documento: 2026-11-16.

## 1. Resumen

Quise migrar el repositorio Bridges Note (API REST en Laravel que sincroniza datos del MINEDU/ESCALE) a PHP 8.3, en cuatro pasos: (1) plan de trabajo con un agente de IA antes de cambiar nada; (2) migración realizada con el agente; (3) commits; (4) push.
Trabajé con Claude Code en VS Code. Primero le pedí un análisis de solo lectura que terminó en un plan, y lo aprobé con algunos ajustes.
Migré de Laravel 7.30.7 a **Laravel 13.42.0 sobre PHP 8.3.36**, partiendo de un esqueleto nuevo de Laravel 13 y portando el código propio, en 14 commits atómicos.
La API conserva las mismas 4 rutas, el mismo formato JSON y los mismos 404. `LocationController` no se modificó.
Resultado: `composer validate --strict` sin errores, `composer audit` sin avisos, 22 pruebas en verde (126 aserciones) contra MariaDB, y la rama subida a `origin`.

## 2. Entorno

Versiones obtenidas ejecutando los comandos el 2026-11-16 (ver la sección 8 para la salida completa).

| Elemento | Versión | Comando |
|---|---|---|
| Sistema operativo | Microsoft Windows 11 Pro 10.0.26300 | `Get-CimInstance Win32_OperatingSystem` |
| PHP | 8.3.36 (cli, NTS, x64), distribuido por Laravel Herd | `php -v` |
| Composer | 2.10.3 | `composer --version` |
| Base de datos | MariaDB 10.4.32 (XAMPP) en 127.0.0.1:3306 | `SELECT VERSION();` |
| Git | 2.55.0.windows.5 | `git --version` |
| Editor | Visual Studio Code 1.146.0 | `code --version` |
| Agente | Claude Code (extensión de VS Code 2.1.325; CLI 2.1.310), modelo Claude Opus 5.5 | `claude --version` y carpeta de extensiones |
| Fecha de la tarea | 2026-11-16 (commits entre 18:58 y 19:17, UTC−05:00) | `git log` |

Herramientas de verificación usadas durante la migración: PHPUnit 12.5, Larastan 3.15 (PHPStan 2.3, nivel 5), Laravel Pint 1.32 (solo `--test`) y PHPCompatibility 10.0.0-alpha2 (ejecutado fuera del repositorio).

## 3. Estado inicial (commit `e947779`)

| Elemento | Valor en `e947779` | Fuente |
|---|---|---|
| Versión de PHP exigida | `"php": "^7.2.5\|^8.0"` | `composer.json`, línea 11 |
| Restricción de Laravel | `"laravel/framework": "^7.29"` | `composer.json`, línea 15 |
| Laravel instalado | `v7.30.7` | `composer.lock`, línea 958 |

**Por qué no corría en PHP 8.3:** Laravel 7 no es compatible con PHP 8.1 o superior. En PHP 8.1 la interfaz `ArrayAccess` pasó a exigir tipos de retorno, y las clases del framework (por ejemplo `Illuminate\Support\Collection`) provocan un error fatal al arrancar. El propio README original lo documentaba:

- Línea 56: *"Laravel 7 no es compatible con PHP 8.1 o superior debido a cambios en la interfaz `ArrayAccess`."*
- Línea 285: *"Error: `During inheritance of ArrayAccess: Return type of ... should either be compatible`"*

Notas del análisis inicial:
- El código propio (`app/`) no tenía errores de sintaxis en PHP 8.3 (`php -l`); el bloqueo era el framework.
- Al hacer el análisis no existía `vendor/`, así que el error de `ArrayAccess` **no se reprodujo** en esta máquina (instalar dependencias estaba prohibido en esa fase). Queda respaldado por el README original y por la incompatibilidad conocida de Laravel 7.

## 4. Historial previo a la migración

Commits que ya existían antes de empezar (`git log e947779`):

| Hash | Fecha | Mensaje |
|---|---|---|
| `e947779` | 2026-11-12 10:25 | iniciando migracion a 8.3 |
| `977e8f7` | 2026-11-09 20:07 | docs: actualizar README detallado con arquitectura, instalacion y endpoints de la API |
| `72154ea` | 2026-11-09 20:02 | fix: migraciones y sincronizacion de ubicaciones y colegios con idDistrito |
| `c1d62b4` | 2026-11-06 00:33 | Punto inicial luego descomprimir |

## 5. Fase 1: plan de trabajo (antes de cambiar nada)

### Cómo se hizo

Le pedí al agente **solo analizar y planificar**, sin modificar archivos, sin instalar dependencias y sin hacer commits. En esta fase solo se usaron comandos de lectura:

- `php -v`, `php -m`, `php -l` sobre todos los `.php`;
- `composer check-platform-reqs --lock`, `composer why-not laravel/framework ^12.0/^13.0 --locked` y `composer show -a` (consultas a Packagist);
- `SELECT VERSION()` contra la base de datos.

Revisó el repositorio entero, no una muestra.

### Hallazgos principales

| Hallazgo | Archivo | Solución propuesta |
|---|---|---|
| Dependencias de la época de L7 (`fideloper/proxy`, `fruitcake/laravel-cors`, `facade/ignition`, `swiftmailer`, `tinker` 2.10) que bloquean la subida | `composer.json`, `composer.lock` | Usar el set de dependencias de Laravel 13 |
| No existe `public/`: si se sirve la raíz, `.env` queda descargable (riesgo alto) | `index.php`, `.htaccess` en la raíz | Mover a `public/` |
| `server.php` busca `public/index.php`, que no existe, así que `php artisan serve` estaba roto | `server.php` | Eliminarlo |
| `$commands` declarado en el kernel HTTP (no tiene efecto) | `app/Http/Kernel.php:9-11` | Desaparece con el Kernel |
| `Request::HEADER_X_FORWARDED_ALL` no existe en Symfony ≥ 6 | `app/Http/Middleware/TrustProxies.php:22` | Usar el TrustProxies nativo |
| Ruta `/api/user` que nunca funcionó (guard `token` sin columna `api_token`) | `routes/api.php:24-26` | Eliminarla |
| Modelo vacío que duplica a `App\Models\Region` | `app/Region.php` | Eliminarlo |
| `HasFactory` importado pero sin usar | `app/Models/*.php:5` | Usar el trait y crear factories |
| Parche `class_exists` en una migración | `2024_12_22_011120_create_schools_table.php` | Migraciones anónimas con los mismos nombres de archivo |
| Las migraciones `0001_01_01_*` del esqueleto chocarían con BD existentes | (esqueleto L13) | No incorporarlas |
| Los valores por defecto de L11+ (`SESSION/CACHE/QUEUE=database`) exigen tablas | `.env.example` | Fijar `file`/`file`/`sync` |
| En PHP 8, `count(null)` y "Undefined array key" abortan la sincronización | `SyncLocations.php:44-46, 121-132, 186-188` | `?? []` y `?? null` |
| `whereRaw('LEFT(...)')` no funciona en SQLite | `LocationController.php:28, 47` | No tocarlo; probar contra MySQL |
| La base local es MariaDB 10.4.32 (sin soporte desde jun-2024) | entorno | Documentarlo |

### Versión elegida: Laravel 13

| Criterio | Laravel 12 | Laravel 13 |
|---|---|---|
| PHP | 8.2 – 8.5 | 8.3 – 8.5 |
| Soporte al 2026-11-16 | Solo parches de seguridad, hasta el 24-feb-2027 | Corrección de bugs hasta Q3-2027; seguridad hasta el 17-mar-2028 |
| Paquetes clave | Disponibles | Tinker 3, Collision 8.9, PHPUnit 12 y Larastan 3.15 ya lo soportan |

Laravel 12 habría obligado a migrar otra vez en unos 3 meses. Antes de ejecutar se confirmó en la documentación oficial que Laravel 13 exige **MariaDB 10.3+**, así que la 10.4.32 local está soportada.

### Estrategia elegida: esqueleto nuevo (B) en lugar de saltos 7→8→…→13 (A)

Los saltos intermedios (L7, L8 y L9) no arrancan en PHP 8.3, así que no se podrían verificar en este entorno. Además, el código propio es pequeño (`SyncLocations`, `LocationController`, 4 modelos, 4 migraciones y 4 rutas). Con un esqueleto nuevo se obtiene la estructura canónica de Laravel 13 y queda claro qué es código propio y qué es del framework.

### Etapas del plan

0. Guardar el plan aprobado.
1. Esqueleto de L13: dependencias, `public/` y bootstrap, y eliminación de los restos de L7.
2. Portar el código propio: rutas y middleware, migraciones, modelos y factories.
3. Tests de los 4 endpoints contra MySQL.
4. Tests de `sync:locations` con `Http::fake()`.
5. Correcciones de PHP 8 en `SyncLocations`.
6. Larastan, Pint y PHPCompatibility.
7. README.

Plan completo: [docs/PLAN_MIGRACION_PHP83.md](PLAN_MIGRACION_PHP83.md).

### Preguntas del agente, mis respuestas y los ajustes que pedí

| # | Pregunta del agente | Mi respuesta |
|---|---|---|
| 1 | ¿Qué hacer con el frontend (Laravel Mix)? | Eliminar `package.json`, `webpack.mix.js`, `resources/js` y `resources/sass`. No agregar Vite. Conservar `/` y el `welcome.blade.php` original. |
| 2 | ¿Conservar las migraciones `users`, `password_resets` y `failed_jobs`? | Conservarlas tal cual; no incorporar las `0001_01_01_*`. |
| 3 | ¿Agregar casts para `pointX`, `pointY` y `zoom`? | Sin casts. Comprobar el supuesto cambio de `zoom`; si no se puede demostrar el "antes", documentar solo lo observado. |
| 4 | ¿Cambiar el exit code de `sync:locations`? | Mantener 0. |
| 5 | ¿Incluir CI? | No. |
| 6 | ¿Permisos para la siguiente fase? | Autorizados: rama, `create-project` en el scratchpad, `composer install`, crear `escalebridge` y `escalebridge_test`. `migrate:fresh` solo sobre `escalebridge_test`. |

Ajustes que pedí antes de aprobar:
- Rama `feat/migracion-php-8.3`.
- Verificar la versión mínima de MariaDB antes de la etapa 1.
- Guardar el plan en el primer commit.
- Dividir la etapa 1 y la etapa 2 en al menos 3 commits cada una.
- Mover `web.config`, `favicon.ico` y `robots.txt` a `public/`.
- Limitar la etapa 5 a `?? []` / `?? null`.
- Usar Pint solo con `--test`.
- Dejar un test de que `/` responde 200.
- Ir una etapa a la vez, mostrando la verificación de cada una.

## 6. Fase 2: ejecución

Commits de la migración (`git log e947779..HEAD`, en orden cronológico, fechas en UTC−05:00):

| # | Hash | Fecha | Mensaje | Etapa |
|---|---|---|---|---|
| 1 | `39a2200` | 2026-11-16 18:58 | docs: agregar plan de migración a PHP 8.3 aprobado | 0 |
| 2 | `eeda961` | 2026-11-16 19:00 | build!: actualizar dependencias a Laravel 13 y PHP ^8.3 | 1 |
| 3 | `c53b77b` | 2026-11-16 19:01 | build: adoptar public/ y bootstrap/app.php de Laravel 13 | 1 |
| 4 | `f7f0f93` | 2026-11-16 19:02 | refactor!: eliminar restos de Laravel 7 y adoptar config de Laravel 13 | 1 |
| 5 | `5bb2260` | 2026-11-16 19:02 | chore: eliminar el frontend de Laravel Mix | 1 |
| 6 | `213b382` | 2026-11-16 19:02 | refactor: registrar rutas /api y middleware en bootstrap/app.php | 2 |
| 7 | `1eff237` | 2026-11-16 19:03 | refactor: convertir migraciones a clases anónimas | 2 |
| 8 | `23faa61` | 2026-11-16 19:04 | refactor: mover User a App\Models, factories por clase y .env.example | 2 |
| 9 | `1777593` | 2026-11-16 19:07 | test: cubrir los 4 endpoints de la API contra MySQL | 3 |
| 10 | `cff1ece` | 2026-11-16 19:08 | test: cubrir sync:locations con Http::fake | 4 |
| 11 | `43106a7` | 2026-11-16 19:09 | fix(sync): tolerar claves ausentes en respuestas de ESCALE con PHP 8 | 5 |
| 12 | `672cb44` | 2026-11-16 19:16 | chore: añadir Larastan nivel 5 y retirar test placeholder | 6 |
| 13 | `7080e3e` | 2026-11-16 19:16 | style: corregir avisos de Pint en líneas introducidas por la migración | 6 |
| 14 | `0096a47` | 2026-11-16 19:17 | docs: actualizar README a PHP 8.3 / Laravel 13 | 7 |

Qué hizo cada etapa:

| Etapa | Resumen |
|---|---|
| 0 | Plan aprobado en `docs/PLAN_MIGRACION_PHP83.md`. |
| 1 | Dependencias de Laravel 13, `public/` como docroot y `bootstrap/app.php` nuevo; se eliminaron los kernels, middleware y providers de L7 y el frontend de Mix. |
| 2 | Rutas `/api` con `throttle:60,1`, CORS nativo, migraciones anónimas con los mismos nombres de archivo, `User` en `App\Models`, factories y `.env.example`. |
| 3 | 11 tests de los endpoints (formato, filtros `LEFT()`, 404 exactos, throttle y CORS) y test de `/` → 200. |
| 4 | 7 tests de `sync:locations` con `Http::fake()` y `Http::preventStrayRequests()`, sin llamadas al MINEDU real. |
| 5 | `?? []` / `?? null` en `SyncLocations`; tests que antes fallaban con "Undefined array key". |
| 6 | Larastan nivel 5 (0 errores), Pint `--test`, PHPCompatibility 8.3 (0 errores). |
| 7 | README actualizado a PHP 8.3 / Laravel 13. |

Push: `git push -u origin feat/migracion-php-8.3` creó la rama remota. `HEAD` y `origin/feat/migracion-php-8.3` apuntan al mismo commit (`0096a47f72a8fd06b6d5480b54e02b2a7c188258`).

## 7. Qué cambió y qué NO cambió

### Rutas de la API (sin cambios)

| Método | Ruta | Respuesta 200 | Respuesta 404 |
|---|---|---|---|
| GET | `/api/regiones` | `{"status":"success","data":[...]}` (si no hay datos, `data: []`; nunca da 404) | — |
| GET | `/api/provincias/{idRegion}` | `{"status":"success","data":[...]}` | `{"status":"error","message":"No se encontraron provincias para esta región."}` |
| GET | `/api/distritos/{idProvince}` | `{"status":"success","data":[...]}` | `{"status":"error","message":"No se encontraron distritos para esta provincia."}` |
| GET | `/api/colegios/{ubigeo}` | `{"status":"success","data":[...]}` | `{"status":"error","message":"No se encontraron escuelas para este distrito."}` |

Se mantienen el límite de 60 peticiones por minuto (`X-RateLimit-Limit: 60`) y CORS abierto (`Access-Control-Allow-Origin: *`), y las pruebas lo verifican. La única ruta eliminada es `GET /api/user`, que nunca fue funcional.

### `LocationController` sin cambios

```text
$ git diff --stat e947779..HEAD -- app/Http/Controllers/LocationController.php
(sin salida)

$ git diff e947779..HEAD -- app/Http/Controllers/LocationController.php | wc -l
0
```

### Resumen de archivos (`git diff --stat e947779..HEAD`)

**87 files changed, 4926 insertions(+), 3798 deletions(-)**

| Tipo | Cantidad | Ejemplos |
|---|---|---|
| Agregados | 11 | `bootstrap/providers.php`, `public/index.php`, `phpstan.neon`, `docs/PLAN_MIGRACION_PHP83.md`, 4 factories, `tests/Feature/Api/LocationApiTest.php`, `tests/Feature/SyncLocationsTest.php`, `tests/Unit/.gitkeep` |
| Modificados | 36 | `composer.json`, `composer.lock`, `bootstrap/app.php`, `config/*`, 7 migraciones, 4 modelos, `SyncLocations.php`, `routes/api.php`, `phpunit.xml`, `README.md`, `.env.example` |
| Eliminados | 34 | `app/Http/Kernel.php`, `app/Console/Kernel.php`, `app/Exceptions/Handler.php`, `app/Http/Middleware/*`, 4 providers, `app/Region.php`, `index.php`, `server.php`, `package.json`, `webpack.mix.js`, `resources/js`, `resources/sass`, `resources/lang`, `config/{broadcasting,hashing,view}.php` |
| Renombrados/movidos | 6 | `.htaccess`, `favicon.ico`, `robots.txt` y `web.config` → `public/`; `app/User.php` → `app/Models/User.php`; `database/seeds` → `database/seeders` |

<details>
<summary>Salida completa de <code>git diff --stat e947779..HEAD</code></summary>

```text
 .env.example                                       |   48 +-
 .gitignore                                         |    2 +
 .styleci.yml                                       |   13 -
 README.md                                          |  185 +-
 app/Console/Commands/SyncLocations.php             |   12 +-
 app/Console/Kernel.php                             |   41 -
 app/Exceptions/Handler.php                         |   55 -
 app/Http/Controllers/Controller.php                |    9 +-
 app/Http/Kernel.php                                |   70 -
 app/Http/Middleware/Authenticate.php               |   21 -
 app/Http/Middleware/CheckForMaintenanceMode.php    |   17 -
 app/Http/Middleware/EncryptCookies.php             |   17 -
 app/Http/Middleware/RedirectIfAuthenticated.php    |   27 -
 app/Http/Middleware/TrimStrings.php                |   18 -
 app/Http/Middleware/TrustHosts.php                 |   20 -
 app/Http/Middleware/TrustProxies.php               |   23 -
 app/Http/Middleware/VerifyCsrfToken.php            |   17 -
 app/Models/District.php                            |    3 +
 app/Models/Province.php                            |    3 +
 app/Models/Region.php                              |    4 +
 app/Models/School.php                              |    3 +
 app/{ => Models}/User.php                          |   12 +-
 app/Providers/AppServiceProvider.php               |    8 +-
 app/Providers/AuthServiceProvider.php              |   30 -
 app/Providers/BroadcastServiceProvider.php         |   21 -
 app/Providers/EventServiceProvider.php             |   34 -
 app/Providers/RouteServiceProvider.php             |   80 -
 app/Region.php                                     |   10 -
 bootstrap/app.php                                  |   70 +-
 bootstrap/providers.php                            |    7 +
 composer.json                                      |   72 +-
 composer.lock                                      | 5523 ++++++++++++--------
 config/app.php                                     |  160 +-
 config/auth.php                                    |   52 +-
 config/broadcasting.php                            |   59 -
 config/cache.php                                   |   62 +-
 config/database.php                                |  103 +-
 config/filesystems.php                             |   37 +-
 config/hashing.php                                 |   52 -
 config/logging.php                                 |   72 +-
 config/mail.php                                    |   90 +-
 config/queue.php                                   |   80 +-
 config/services.php                                |   17 +-
 config/session.php                                 |  110 +-
 config/view.php                                    |   36 -
 database/factories/DistrictFactory.php             |   23 +
 database/factories/ProvinceFactory.php             |   25 +
 database/factories/RegionFactory.php               |   26 +
 database/factories/SchoolFactory.php               |   29 +
 database/factories/UserFactory.php                 |   45 +-
 .../2014_10_12_000000_create_users_table.php       |   12 +-
 ...4_10_12_100000_create_password_resets_table.php |   12 +-
 .../2019_08_19_000000_create_failed_jobs_table.php |   12 +-
 .../2024_12_22_010708_create_regions_table.php     |    8 +-
 .../2024_12_22_010930_create_provinces_table.php   |    8 +-
 .../2024_12_22_011019_create_districts_table.php   |    8 +-
 .../2024_12_22_011120_create_schools_table.php     |   42 +-
 database/{seeds => seeders}/DatabaseSeeder.php     |    6 +-
 docs/PLAN_MIGRACION_PHP83.md                       |   52 +
 index.php                                          |   60 -
 package.json                                       |   21 -
 phpstan.neon                                       |   11 +
 phpunit.xml                                        |   40 +-
 .htaccess => public/.htaccess                      |    4 +
 favicon.ico => public/favicon.ico                  |    0
 public/index.php                                   |   20 +
 robots.txt => public/robots.txt                    |    0
 web.config => public/web.config                    |    0
 resources/js/app.js                                |    1 -
 resources/js/bootstrap.js                          |   28 -
 resources/lang/en/auth.php                         |   19 -
 resources/lang/en/pagination.php                   |   19 -
 resources/lang/en/passwords.php                    |   22 -
 resources/lang/en/validation.php                   |  151 -
 resources/sass/app.scss                            |    1 -
 routes/api.php                                     |   13 +-
 routes/channels.php                                |   18 -
 routes/console.php                                 |   13 +-
 server.php                                         |   21 -
 tests/CreatesApplication.php                       |   22 -
 tests/Feature/Api/LocationApiTest.php              |  173 +
 tests/Feature/ExampleTest.php                      |    7 +-
 tests/Feature/SyncLocationsTest.php                |  298 ++
 tests/TestCase.php                                 |   16 +-
 tests/Unit/.gitkeep                                |    0
 tests/Unit/ExampleTest.php                         |   18 -
 webpack.mix.js                                     |   15 -
 87 files changed, 4926 insertions(+), 3798 deletions(-)
```

</details>

## 8. Verificación

Comandos ejecutados de nuevo el 2026-11-16 al redactar este documento. `php artisan test` usa la base `escalebridge_test` (fijada en `phpunit.xml` y protegida por `tests/TestCase.php`). Todos terminaron con código de salida 0.

```text
$ php -v
PHP 8.3.36 (cli) (built: Oct 20 2026 04:18:57) (NTS Visual C++ 2019 x64)
Copyright (c) The PHP Group
Zend Engine v4.3.36, Copyright (c) Zend Technologies
    with Zend OPcache v8.3.36, Copyright (c), by Zend Technologies

$ composer --version
Composer version 2.10.3 2026-08-27 13:34:23
PHP version 8.3.36 (C:\Users\Geral\.config\herd\bin\php83\php.exe)
Run the "diagnose" command to get more detailed diagnostics output.

$ composer validate --strict
./composer.json is valid

$ composer audit
No security vulnerability advisories found.

$ php artisan --version
Laravel Framework 13.42.0

$ php artisan route:list --path=api

  GET|HEAD       api/colegios/{ubigeo} ................................................. LocationController@getSchools
  GET|HEAD       api/distritos/{idProvince} .......................................... LocationController@getDistricts
  GET|HEAD       api/provincias/{idRegion} ........................................... LocationController@getProvinces
  GET|HEAD       api/regiones .......................................................... LocationController@getRegions

                                                                                                    Showing [4] routes

$ php artisan test

   PASS  Tests\Feature\Api\LocationApiTest
  ✓ regiones devuelve todas las regiones con formato success                                                     0.66s
  ✓ regiones sin datos devuelve 200 con lista vacia                                                              0.04s
  ✓ provincias filtra por los dos primeros digitos                                                               0.04s
  ✓ provincias sin coincidencias devuelve 404                                                                    0.07s
  ✓ provincias compara exactamente dos caracteres                                                                0.04s
  ✓ distritos filtra por los cuatro primeros digitos                                                             0.03s
  ✓ distritos sin coincidencias devuelve 404                                                                     0.02s
  ✓ colegios filtra por ubigeo completo                                                                          0.03s
  ✓ colegios sin coincidencias devuelve 404                                                                      0.02s
  ✓ api conserva throttle 60 por minuto y cors abierto                                                           0.02s
  ✓ ruta user eliminada no existe                                                                                0.02s

   PASS  Tests\Feature\ExampleTest
  ✓ la raiz responde 200                                                                                         0.04s

   PASS  Tests\Feature\SyncLocationsTest
  ✓ sincroniza regiones provincias distritos y colegios en formato lista                                         0.11s
  ✓ envia los parametros esperados a escale                                                                      0.03s
  ✓ acepta respuestas de un solo objeto en vez de lista                                                          0.04s
  ✓ distrito sin items informa y continua                                                                        0.04s
  ✓ error http en provincias informa y mantiene exit code 0                                                      0.04s
  ✓ error http en regiones no guarda nada y mantiene exit code 0                                                 0.03s
  ✓ respuesta de regiones sin clave region no rompe el comando                                                   0.03s
  ✓ respuestas sin clave provincia o distrito no rompen el comando                                               0.05s
  ✓ colegios en lista sin cenedu cenpob o dircen se guardan con null                                             0.08s
  ✓ es idempotente y actualiza registros existentes                                                              0.07s

  Tests:    22 passed (126 assertions)
  Duration: 2.11s
```

Durante la migración también se ejecutaron (no repetidos al redactar este documento):
- `composer install` con `vendor/` borrado: 112 paquetes, código de salida 0.
- `vendor/bin/phpstan analyse`: `[OK] No errors`.
- PHPCompatibility con `testVersion 8.3`: 0 errores y 1 aviso que no es real (en `routes/console.php`).
- Una prueba por HTTP real con `php artisan serve` contra `escalebridge_test`: los 4 endpoints respondieron 200 con el formato `{status, data}`, y `/api/provincias/99` respondió el 404 esperado.

`sync:locations` **no** se ejecutó contra el MINEDU real en ningún momento. La base `escalebridge` quedó sin tablas.

## 9. Decisiones y desviaciones del plan

| Tema | Qué pasó |
|---|---|
| Rutas `storage/{path}` | La config de Laravel 13 (`config/filesystems.php`, `'serve' => true`) registraba dos rutas que la app original no tenía. Se pusieron en `'serve' => false` para mantener las mismas rutas. |
| Ruta `/up` | El esqueleto de Laravel 13 incluye `health: '/up'`. No se incorporó porque no forma parte de la API original. |
| Caso `zoom` | No se pudo demostrar cómo salía antes: no hay PHP 7.x en la máquina (Herd solo tiene 8.3 y XAMPP trae 8.2.12). Laravel 13 usa `ATTR_EMULATE_PREPARES => false`, y en PHP 8.3 se observa `zoom` como entero y `pointX`/`pointY` como texto (`"-12.0463740"`). Se documentó solo lo observado, **sin presentarlo como un cambio**, y sin casts. |
| Pint | Solo `--test`. Se corrigieron únicamente los avisos introducidos por la migración (docblocks con FQCN en modelos, `@return void` redundante y un import en un test). El estilo del código original (`SyncLocations`, `LocationController`, espaciado de propiedades) no se reformateó. Los avisos `line_ending` vienen de `core.autocrlf=true` en el equipo, no del contenido. |
| Protección en `tests/TestCase.php` | No estaba en el plan. Se añadió para que las pruebas se aborten si la base no es `escalebridge_test`, porque `RefreshDatabase` borra tablas. |
| Test placeholder | Se eliminó `tests/Unit/ExampleTest.php` (`assertTrue(true)`), que Larastan marcaba como siempre verdadero, y se dejó `tests/Unit/.gitkeep`. |
| Comandos bloqueados por permisos | El sistema de permisos del agente bloqueó dos comandos, con razón: (1) un `migrate:fresh --env=testing` que, al no existir `.env.testing`, podía apuntar a `escalebridge`; (2) un `taskkill /IM php.exe` que habría cerrado todos los procesos PHP, Herd incluido. Se rehicieron de forma segura: base de datos fijada y comprobada antes de migrar, y servidor detenido por su número de proceso. |
| Mensaje del commit `eeda961` | Dice "La app NO arranca en este commit". Es inexacto: `php artisan --version` ya respondía (Laravel 13.42.0); lo que fallaba era la parte web, porque el kernel HTTP de L7 seguía referenciando `Fruitcake\Cors`. No se reescribió el historial para corregirlo. |
| Otros incidentes técnicos | En PowerShell 5.1 las comillas dobles partieron un mensaje de commit (no se creó ningún commit erróneo), y `composer.bat` eliminó el `^` de `larastan/larastan:^3.15`, que se corrigió antes de hacer el commit. |

**Nota de proceso:** pedí avanzar "una etapa a la vez" y ver la verificación de cada una antes de seguir. Entre etapas el agente mostró los resultados, pero **no esperó mi respuesta** para continuar: interpretó que debía completar toda la migración en la misma sesión. Ninguna etapa falló, pero no tuve ocasión de detener el proceso entre etapas.

## 10. Pendientes y mejoras futuras

| Tema | Detalle |
|---|---|
| Exposición de `.env` | En el código original se servía la raíz del repositorio, y así `.env`, `composer.json` y `storage/logs` quedaban descargables. La migración crea `public/`, pero el servidor de producción debe apuntar su raíz web a `public/`. **No verificado** en el entorno de producción. |
| MariaDB 10.4 | Sin soporte desde junio de 2024. Funciona con Laravel 13 (mínimo 10.3), pero conviene actualizarla. |
| Errores de conexión en `sync:locations` | Un timeout o una caída de red (`ConnectionException`) aborta toda la sincronización. Se propone capturarla por paso, con `Http::retry()` y `timeout()`. Se dejó fuera a propósito para no cambiar el comportamiento. |
| Pint sobre el código original | `vendor/bin/pint --test` todavía reporta formato en `SyncLocations.php`, `LocationController.php` y los modelos. Pendiente de aprobación. |
| Pull request | La rama está subida; el PR aún no se ha creado. |
| URL del README | El README conserva la URL de clonado original (`hubelsolis/brigesnote`), mientras que el remoto actual es `Geralni/brigesnote`. |

## 11. Cómo reproducir

Requisitos: PHP 8.3+, Composer 2 y MySQL 5.7+ o MariaDB 10.3+.

```bash
git clone -b feat/migracion-php-8.3 https://github.com/Geralni/brigesnote.git
cd brigesnote
composer install
cp .env.example .env
php artisan key:generate
```

Crear las bases (`utf8mb4_unicode_ci`):

```sql
CREATE DATABASE escalebridge      CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE escalebridge_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Migrar y verificar:

```bash
php artisan migrate                    # usa DB_DATABASE=escalebridge del .env
composer validate --strict
php artisan route:list --path=api      # 4 rutas
php artisan test                       # usa escalebridge_test (phpunit.xml)
vendor/bin/phpstan analyse             # Larastan nivel 5
php artisan serve                      # http://127.0.0.1:8000/api/regiones
```

`php artisan sync:locations` descarga el catálogo real del MINEDU (más de 1,800 distritos y 80,000 colegios). No es necesario para las pruebas, que simulan ESCALE con `Http::fake()`.

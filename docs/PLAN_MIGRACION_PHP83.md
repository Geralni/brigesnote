# Plan de migración a PHP 8.3 — Bridges Note

Estado: **aprobado** (16-nov-2026). Rama de trabajo: `feat/migracion-php-8.3`.

## Contexto

- Origen: Laravel 7.30.7 (`composer.json`: `php ^7.2.5|^8.0`). Laravel 7 no arranca en PHP ≥ 8.1
  (error de `ArrayAccess` en `Illuminate\Support\Collection`). El código propio no tiene errores
  de sintaxis en 8.3 (`php -l`): el bloqueo es el framework.
- Entorno: PHP 8.3.36 (Herd, NTS), Composer 2.10.3, MariaDB 10.4.32 (XAMPP) en 127.0.0.1:3306.
  Extensiones requeridas presentes (`composer check-platform-reqs --lock` en verde).

## Decisiones

| Tema | Decisión |
|---|---|
| Versión objetivo | **Laravel 13** (PHP 8.3–8.5). L12 solo tiene parches de seguridad hasta el 24-feb-2027; L13 recibe corrección de bugs hasta Q3-2027 y seguridad hasta el 17-mar-2028. Tinker 3, Collision 8.9, PHPUnit 12 y Larastan 3.x ya soportan L13. |
| MariaDB | La documentación de L13 exige MariaDB 10.3+; la versión local 10.4.32 está soportada. Se mantiene el driver `mysql`. |
| Estrategia | **B: esqueleto nuevo de L13 + portar el código propio** (SyncLocations, LocationController, 4 modelos, 4 migraciones, rutas, CORS). Los saltos 7→8→…→13 no se pueden verificar en PHP 8.3 en los pasos intermedios. |
| Frontend | Se eliminan `package.json`, `webpack.mix.js`, `resources/js` y `resources/sass`. No se agrega Vite. Se conservan la ruta `/` y el `welcome.blade.php` original (sin `@vite`). |
| Migraciones | Se conservan tal cual `users`, `password_resets` y `failed_jobs` (mismos nombres de archivo). **No** se incorporan las `0001_01_01_*` del esqueleto. |
| JSON | Sin casts. No se documenta ningún cambio de tipo que no se pueda demostrar. |
| `sync:locations` | El exit code sigue siendo 0. En la etapa 5 solo se añaden `?? []` y `?? null`; el try/catch de `ConnectionException` queda como mejora futura. |
| Archivos web | `web.config`, `favicon.ico` y `robots.txt` pasan a `public/`. |
| CI | Fuera de alcance. |
| Base de datos | Se crean `escalebridge` y `escalebridge_test`. `migrate:fresh` solo sobre `escalebridge_test`. |
| Datos reales | No se ejecuta `sync:locations` contra el MINEDU; los tests usan `Http::fake()` + `preventStrayRequests()`. |

## Etapas y commits

0. `docs: agregar plan de migración a PHP 8.3 aprobado`
1. Esqueleto L13 (≥3 commits): composer.json + lock; `public/` + `bootstrap/app.php`; eliminación de restos de L7.
2. Portar el código (≥3 commits): rutas y middleware; migraciones; modelos, factories y `.env.example`.
3. `test: cubrir los 4 endpoints de la API contra MySQL` (incluye `/` → 200).
4. `test: cubrir sync:locations con Http::fake`.
5. `fix(sync): tolerar claves ausentes en respuestas de ESCALE con PHP 8` (solo `?? []` / `?? null`).
6. Larastan (en el repo), Pint solo con `--test`, PHPCompatibility (testVersion 8.3) fuera del repo.
7. `docs: actualizar README y .env.example a PHP 8.3 / Laravel 13`.

**Verificación común (V) en cada etapa:** `php -l` recursivo, `composer validate --strict`, `composer audit`,
`php artisan about`, `php artisan route:list --path=api`.

## Criterio de terminado

`php -v` 8.3; `composer install` limpio; `composer validate --strict` sin errores;
`route:list --path=api` muestra las 4 rutas; `php artisan test` en verde contra `escalebridge_test`;
las 4 rutas `/api` responden con el mismo formato `{status, data}` y los mismos 404 `{status:"error", message}`.

## Fuera de alcance

Frontend/Vite, rediseño de la sincronización (colas, paginación, reintentos), actualizar MariaDB 10.4
(sin soporte desde jun-2024), autenticación, versionado de la API, Docker y CI.

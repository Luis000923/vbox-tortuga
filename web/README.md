# Quitar Tortuga VBox — API + Web

Proyecto de soporte para el script que desactiva Hyper-V/VBS y libera VT-x/AMD-V
para VirtualBox. Autor: **Luis Vides — Tec. en Ciberseguridad**.

Cada ejecución del script envía un inventario de hardware a esta API PHP. La web
muestra en qué dispositivos ha funcionado, permite reportar fallos y solicitar
colaboración. **La clave de recuperación BitLocker nunca sale del equipo.**

## Estructura

```
config/            (FUERA de public_html en el servidor)
  .env.example     copiar a .env con credenciales reales
  db.php           conexion PDO + lector de .env
  helpers.php      seguridad, validacion, rate limit, CSRF, firma hardware
public_html/       docroot
  index.php        landing (dispositivos OK + comentarios)
  reportar.php     formulario de reporte web
  colaborar.php    formulario para colaborar
  assets/style.css
  api/             submit, report, comment, access, devices
  admin/           index (login), panel, logout, rebuild-cache
  cache/           devices.json (generado por cron)
  .htaccess
sql/schema.sql     tablas
```

## Despliegue en Hostinger (shared hosting)

1. **Base de datos**: hPanel → Bases de datos MySQL → crear base + usuario.
   Abrir phpMyAdmin → Importar → `sql/schema.sql`.

2. **Subir archivos** (File Manager o FTP):
   - Contenido de `public_html/` → carpeta `public_html/` del hosting.
   - Carpeta `config/` → un nivel **por encima** de `public_html`
     (ej. `/home/uXXXX/config/`). Nunca dentro del docroot.
   - `sql/` no hace falta subirla (solo se usa para importar).

3. **Configurar `.env`**: copiar `config/.env.example` a `config/.env` y rellenar:
   - Datos de la DB.
   - `API_KEY_HASH`: genera una clave y su hash:
     ```
     php -r "echo bin2hex(random_bytes(24)).PHP_EOL;"          # -> esta va en el script ($ApiKey)
     php -r "echo hash('sha256','LA_CLAVE').PHP_EOL;"          # -> esta va en API_KEY_HASH
     ```
   - `CRON_SECRET`: `php -r "echo bin2hex(random_bytes(16)).PHP_EOL;"`

4. **Ajustar rutas de `config/` si es necesario**: los PHP buscan `config/`
   como hermano de `public_html` vía `dirname(__DIR__, N)`. Si tu hosting usa otra
   disposición, mueve `config/` junto a `public_html` o ajusta los `require`.

5. **Crear el admin**:
   ```
   php -r "echo password_hash('TU_PASSWORD', PASSWORD_DEFAULT).PHP_EOL;"
   ```
   En phpMyAdmin:
   ```sql
   INSERT INTO admin_users (usuario, password_hash) VALUES ('luis', '<hash>');
   ```

6. **Cron** (hPanel → Trabajos cron), cada 15 min:
   ```
   php /home/uXXXX/public_html/admin/rebuild-cache.php TU_CRON_SECRET
   ```

7. **Permisos**: `public_html/cache/` con escritura para el usuario web.

8. **Script PowerShell**: en `Quitar-Tortuga-VBox.ps1` poner
   `$ApiBaseUrl = "https://tudominio"` y `$ApiKey = "LA_CLAVE_EN_CLARO"`.

## Ejecución por una línea (`irm ... | iex`)

Dos dominios:

- **`vbox.pdsx.org`** (subdominio de ejecución) → docroot = carpeta `run/`.
  Sirve el bootstrap. El usuario ejecuta:
  ```powershell
  irm https://vbox.pdsx.org | iex
  ```
  El bootstrap descarga `https://vbox.pdsx.org/script.ps1` a `%TEMP%` y lo lanza
  elevado (se hace en disco porque `irm|iex` corre en memoria y el script
  necesita un archivo real para autoelevarse y para la tarea de reinicio).

- **`vbox-pdsx.org`** (dominio del proyecto) → docroot = `public_html/`.
  Web + API. Es el valor de `$ApiBaseUrl` en el script.

**Al actualizar el script**: vuelve a copiar la versión final a `run/script.ps1`
(con `$ApiBaseUrl = "https://vbox-pdsx.org"` y `$ApiKey` puesto):
```
cp Quitar-Tortuga-VBox.ps1 web/run/script.ps1
```
`run/.htaccess` ya sirve `*.ps1` como `text/plain` y fuerza HTTPS.

> Nota de seguridad: como el script se descarga públicamente, la `$ApiKey`
> incrustada NO es secreta; funciona solo como filtro básico. La defensa real
> del API es el rate limiting + la validación del servidor.

## QA local con Docker

`qa/docker-compose.yml` levanta sitio+API (`:8080`), bootstrap (`:8081`) y
MariaDB con el esquema y un admin de prueba (`admin` / `admin123`).

```
cd web
docker compose -f qa/docker-compose.yml up -d --build
# sitio/API: http://localhost:8080   ·   bootstrap: http://localhost:8081
docker compose -f qa/docker-compose.yml down -v   # apagar y borrar datos
```

Usa `config/.env` con credenciales de QA (API key en claro: `qa-test-key-123`).
**Ese `.env` es solo para QA local; crea uno propio en producción.**

Pruebas ejecutadas y verificadas en QA: submit con dedup (mismas specs = 1 fila,
sube `veces`), 401 sin API key, 405 en GET, report con diagnóstico, **sin fuga de
UUID/nombre de equipo**, comentarios con CSRF (403 sin token) y moderación,
regeneración de cache, `devices.php`, login admin (ok/ko), y **escape XSS** en la web.

## Prueba local rápida

```
php -S localhost:8000 -t public_html         # requiere MySQL local + config/.env
curl -X POST -H "X-API-Key: LA_CLAVE" -H "Content-Type: application/json" \
     -d '{"CPU":"Intel i5","Nucleos":4,"Hilos":8,"RAM_GB":16,"Placa":"ASUS X","GPU":"UHD 620","WindowsEdicion":"Win 11 Home","WindowsBuild":"22631"}' \
     http://localhost:8000/api/submit.php
# repetir el mismo curl -> no duplica, sube "veces"
php public_html/admin/rebuild-cache.php TU_CRON_SECRET
```

## Seguridad implementada

- PDO con prepared statements (sin SQL dinámico con datos de usuario).
- Secretos en `.env` fuera del docroot.
- API con token: el servidor guarda solo el `sha256` de la clave (`hash_equals`).
- Validación y límites de longitud en toda entrada; payload JSON acotado.
- Escape `htmlspecialchars` en toda salida (anti-XSS).
- CSRF en formularios web y panel admin.
- Rate limiting por IP + endpoint.
- Admin con `password_hash`/`password_verify` y cookie `HttpOnly`+`Secure`+`SameSite`.
- `.htaccess`: HTTPS forzado, cabeceras de seguridad, bloqueo de archivos sensibles.
```

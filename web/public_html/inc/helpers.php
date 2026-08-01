<?php
/**
 * helpers.php - Utilidades compartidas: seguridad, entrada/salida, rate limit.
 * Proyecto: Quitar Tortuga VBox  -  Autor: Luis Vides (Tec. Ciberseguridad)
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';

/* -------------------------------------------------------------------------- */
/* Salida segura                                                              */
/* -------------------------------------------------------------------------- */

/** Escapa texto para insertarlo en HTML (anti-XSS). */
function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Responde JSON y termina. */
function json_out($data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Lee y decodifica el cuerpo JSON de la peticion (con limite de tamano). */
function json_in(int $maxBytes = 65536): array
{
    $raw = file_get_contents('php://input', false, null, 0, $maxBytes + 1);
    if ($raw === false || strlen($raw) > $maxBytes) {
        json_out(['ok' => false, 'error' => 'Payload demasiado grande'], 413);
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        json_out(['ok' => false, 'error' => 'JSON invalido'], 400);
    }
    return $data;
}

/* -------------------------------------------------------------------------- */
/* Entrada / validacion                                                       */
/* -------------------------------------------------------------------------- */

/** Recorta un string a longitud maxima y limpia espacios. */
function str_field($v, int $max): string
{
    return mb_substr(trim((string) $v), 0, $max);
}

/** Entero o null. */
function int_field($v): ?int
{
    return is_numeric($v) ? (int) $v : null;
}

/** Bool a 0/1/null (acepta true/false/1/0/"true"...). */
function bool01($v): ?int
{
    if ($v === null || $v === '') {
        return null;
    }
    return filter_var($v, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ? 1 : 0;
}

/** Solo acepta el metodo HTTP indicado; si no, 405. */
function require_method(string $method): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== $method) {
        json_out(['ok' => false, 'error' => 'Metodo no permitido'], 405);
    }
}

/* -------------------------------------------------------------------------- */
/* Autenticacion del API (token del script)                                   */
/* -------------------------------------------------------------------------- */

/** Verifica el header X-API-Key contra el hash guardado. Si falla, 401. */
function require_api_key(): void
{
    $key  = $_SERVER['HTTP_X_API_KEY'] ?? '';
    $hash = (string) env('API_KEY_HASH', '');
    if ($hash === '' || !hash_equals($hash, hash('sha256', $key))) {
        json_out(['ok' => false, 'error' => 'No autorizado'], 401);
    }
}

/* -------------------------------------------------------------------------- */
/* IP del cliente                                                             */
/* -------------------------------------------------------------------------- */

/** Devuelve la IP en binario (para columna VARBINARY(16)). */
function client_ip_bin(): string
{
    $ip  = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $bin = @inet_pton($ip);
    return $bin !== false ? $bin : inet_pton('0.0.0.0');
}

/* -------------------------------------------------------------------------- */
/* Rate limiting (por IP + endpoint, ventana temporal)                        */
/* -------------------------------------------------------------------------- */

/**
 * Permite como maximo $max peticiones por $seconds. Si se excede, 429.
 * Tambien limpia registros viejos oportunamente.
 */
function rate_limit(string $endpoint, int $max = 10, int $seconds = 60): void
{
    $pdo = db();
    $ip  = client_ip_bin();

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM rate_limit
         WHERE ip = ? AND endpoint = ? AND creado > (NOW() - INTERVAL ? SECOND)'
    );
    $stmt->execute([$ip, $endpoint, $seconds]);

    if ((int) $stmt->fetchColumn() >= $max) {
        json_out(['ok' => false, 'error' => 'Demasiadas peticiones, intenta luego'], 429);
    }

    $pdo->prepare('INSERT INTO rate_limit (ip, endpoint) VALUES (?, ?)')
        ->execute([$ip, $endpoint]);

    // Limpieza oportunista (1 de cada ~20 peticiones).
    if (random_int(1, 20) === 1) {
        $pdo->query('DELETE FROM rate_limit WHERE creado < (NOW() - INTERVAL 1 DAY)');
    }
}

/* -------------------------------------------------------------------------- */
/* CSRF (formularios web)                                                     */
/* -------------------------------------------------------------------------- */

function ensure_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_set_cookie_params([
            'httponly' => true,
            'secure'   => (($_SERVER['HTTPS'] ?? '') === 'on'),
            'samesite' => 'Strict',
        ]);
        session_start();
    }
}

function csrf_token(): string
{
    ensure_session();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

/** Valida el token CSRF del formulario; si falla, 403. */
function csrf_check(): void
{
    ensure_session();
    $sent = $_POST['csrf'] ?? '';
    if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], (string) $sent)) {
        http_response_code(403);
        exit('Token CSRF invalido. Recarga la pagina e intenta de nuevo.');
    }
}

/* -------------------------------------------------------------------------- */
/* Firma de hardware (dedup)                                                  */
/* -------------------------------------------------------------------------- */

/**
 * Calcula la firma unica del hardware a partir del inventario.
 * Se ignoran datos volatiles (nombre de equipo, fecha) para que dos equipos
 * con el mismo modelo/specs compartan firma y NO se dupliquen.
 */
function firma_hardware(array $inv): string
{
    $partes = [
        mb_strtolower(trim((string) ($inv['CPU'] ?? ''))),
        (string) ($inv['Nucleos'] ?? ''),
        (string) ($inv['Hilos'] ?? ''),
        (string) ($inv['RAM_GB'] ?? ''),
        mb_strtolower(trim((string) ($inv['Placa'] ?? ''))),
        mb_strtolower(trim((string) ($inv['GPU'] ?? ''))),
        mb_strtolower(trim((string) ($inv['WindowsEdicion'] ?? ''))),
    ];
    return hash('sha256', implode('|', $partes));
}

/* -------------------------------------------------------------------------- */
/* Cabeceras de seguridad (por si .htaccess no aplica)                        */
/* -------------------------------------------------------------------------- */

function security_headers(): void
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: no-referrer');
    header("Content-Security-Policy: default-src 'self'; style-src 'self'; img-src 'self' data:; script-src 'none'; base-uri 'none'; form-action 'self'");
}

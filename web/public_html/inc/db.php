<?php
/**
 * db.php - Carga de configuracion (.env) y conexion PDO.
 * Proyecto: Quitar Tortuga VBox  -  Autor: Luis Vides (Tec. Ciberseguridad)
 *
 * Este archivo debe vivir FUERA de public_html en el servidor.
 */

declare(strict_types=1);

/**
 * Lee un archivo .env muy simple (CLAVE=valor por linea) y lo cachea.
 */
function env(string $key, ?string $default = null): ?string
{
    static $vars = null;

    if ($vars === null) {
        $vars = [];
        $path = __DIR__ . '/.env';
        if (is_readable($path)) {
            foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                $line = trim($line);
                if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
                    continue;
                }
                [$k, $v] = explode('=', $line, 2);
                $vars[trim($k)] = trim($v);
            }
        }
    }

    return array_key_exists($key, $vars) ? $vars[$key] : $default;
}

/**
 * Devuelve una conexion PDO unica (singleton).
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $host = env('DB_HOST', 'localhost');
        $name = env('DB_NAME', '');
        $user = env('DB_USER', '');
        $pass = env('DB_PASS', '');

        $dsn = "mysql:host={$host};dbname={$name};charset=utf8mb4";

        try {
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            if (env('APP_ENV') === 'development') {
                exit('DB error: ' . $e->getMessage());
            }
            exit('Error de conexion a la base de datos.');
        }
    }

    return $pdo;
}

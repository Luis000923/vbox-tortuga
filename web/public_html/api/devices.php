<?php
/**
 * api/devices.php - Devuelve la lista publica de dispositivos (desde cache).
 * La web NO consulta la DB en vivo: sirve el JSON regenerado por el cron.
 *
 * Metodo: GET.  Publico.
 */

declare(strict_types=1);
require_once dirname(__DIR__) . '/inc/helpers.php';

require_method('GET');
security_headers();

$cache = dirname(__DIR__) . '/cache/devices.json';

header('Content-Type: application/json; charset=utf-8');

if (is_readable($cache)) {
    readfile($cache);
} else {
    echo json_encode(['generado' => null, 'dispositivos' => []]);
}

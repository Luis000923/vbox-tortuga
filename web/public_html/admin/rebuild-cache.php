<?php
/**
 * admin/rebuild-cache.php - Regenera public_html/cache/devices.json desde la DB.
 * Lo llama el CRON de Hostinger (o un admin logueado). Asi la web no consulta
 * la DB en vivo y se cumple "actualiza cada cierto tiempo, no en tiempo real".
 *
 * Uso por cron (cada ~15 min):
 *   php /home/uXXXX/public_html/admin/rebuild-cache.php SECRETO
 * Uso por HTTP:
 *   https://tudominio/admin/rebuild-cache.php?secret=SECRETO
 */

declare(strict_types=1);
require_once dirname(__DIR__) . '/inc/helpers.php';

// --- Autorizacion: secreto del cron, argumento CLI, o sesion admin ---
$secret   = (string) env('CRON_SECRET', '');
$provided = $_GET['secret'] ?? ($argv[1] ?? '');

$esCli = (PHP_SAPI === 'cli');
if (!$esCli) {
    ensure_session();
}
$autorizado = ($secret !== '' && hash_equals($secret, (string) $provided))
    || (!$esCli && !empty($_SESSION['admin_id']));

if (!$autorizado) {
    http_response_code(403);
    exit('No autorizado.');
}

$pdo = db();

$devices = $pdo->query(
    'SELECT firma_hash, cpu, nucleos, hilos, ram_gb, placa, bios, gpu,
            windows_edicion, windows_version, windows_build, arquitectura,
            estado, veces, ultima_fecha
     FROM devices
     ORDER BY estado ASC, veces DESC, ultima_fecha DESC
     LIMIT 500'
)->fetchAll();

$comentarios = $pdo->query(
    'SELECT nombre, mensaje, DATE(creado) AS creado
     FROM comentarios
     WHERE aprobado = 1
     ORDER BY creado DESC
     LIMIT 200'
)->fetchAll();

$payload = [
    'generado'     => date('Y-m-d H:i'),
    'dispositivos' => $devices,
    'comentarios'  => $comentarios,
];

$dir = dirname(__DIR__) . '/cache';
if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}

$json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
file_put_contents($dir . '/devices.json', $json, LOCK_EX);

if ($esCli) {
    echo 'Cache regenerado: ' . count($devices) . " dispositivos.\n";
} else {
    json_out(['ok' => true, 'dispositivos' => count($devices)]);
}

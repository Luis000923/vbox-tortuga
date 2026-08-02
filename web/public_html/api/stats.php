<?php
/**
 * api/stats.php - Contador en vivo: cuantas veces se uso el script con exito
 * y en cuantos equipos distintos. Consulta la DB en cada peticion (a
 * diferencia de devices.php, que sirve el cache regenerado por el cron):
 * es un COUNT/SUM sobre una tabla pequena, barato de correr en vivo.
 *
 * Metodo: GET. Publico. Pensado para sondeo (polling) desde el navegador.
 */

declare(strict_types=1);
require_once dirname(__DIR__) . '/inc/helpers.php';

require_method('GET');
security_headers();
rate_limit('stats', 30, 60);

$row = db()->query(
    'SELECT COUNT(*) AS dispositivos, COALESCE(SUM(veces), 0) AS usos
     FROM devices
     WHERE estado = "funciono"'
)->fetch();

json_out([
    'usos'         => (int) $row['usos'],
    'dispositivos' => (int) $row['dispositivos'],
    'hora'         => date('c'),
]);

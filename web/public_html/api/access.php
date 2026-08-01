<?php
/**
 * api/access.php - Recibe una solicitud para colaborar en el proyecto.
 * Se guarda como 'pendiente' para revisarla en el panel admin.
 *
 * Metodo: POST (formulario).  Proteccion: CSRF + rate limit.
 */

declare(strict_types=1);
require_once dirname(__DIR__) . '/inc/helpers.php';

require_method('POST');
csrf_check();
rate_limit('access', 5, 600);

$nombre = str_field($_POST['nombre'] ?? '', 80);
$email  = str_field($_POST['email'] ?? '', 160);
$github = str_field($_POST['github'] ?? '', 160);
$motivo = str_field($_POST['motivo'] ?? '', 1000);

$emailOk = filter_var($email, FILTER_VALIDATE_EMAIL) !== false;

if ($nombre === '' || !$emailOk || mb_strlen($motivo) < 5) {
    header('Location: /colaborar.php?err=1');
    exit;
}

db()->prepare(
    'INSERT INTO solicitudes_acceso (nombre, email, github, motivo, estado)
     VALUES (?, ?, ?, ?, "pendiente")'
)->execute([$nombre, $email, $github !== '' ? $github : null, $motivo]);

header('Location: /colaborar.php?ok=1');
exit;

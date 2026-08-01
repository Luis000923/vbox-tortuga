<?php
/**
 * api/comment.php - Recibe un comentario/testimonio desde el formulario web.
 * Queda PENDIENTE (aprobado=0) hasta que se apruebe en el panel admin.
 *
 * Metodo: POST (formulario).  Proteccion: CSRF + rate limit.
 */

declare(strict_types=1);
require_once dirname(__DIR__) . '/inc/helpers.php';

require_method('POST');
csrf_check();
rate_limit('comment', 5, 300);

$nombre  = str_field($_POST['nombre'] ?? '', 80);
$mensaje = str_field($_POST['mensaje'] ?? '', 1000);
$firma   = str_field($_POST['firma'] ?? '', 64);
$firma   = preg_match('/^[a-f0-9]{64}$/', $firma) ? $firma : null;

if ($nombre === '' || mb_strlen($mensaje) < 3) {
    header('Location: /index.php?err=comentario#comentarios');
    exit;
}

db()->prepare(
    'INSERT INTO comentarios (firma_hash, nombre, mensaje, aprobado)
     VALUES (?, ?, ?, 0)'
)->execute([$firma, $nombre, $mensaje]);

header('Location: /index.php?ok=comentario#comentarios');
exit;

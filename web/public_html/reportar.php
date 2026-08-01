<?php
/**
 * reportar.php - Formulario web para reportar un bug o que el script no
 * funciono. Alternativa manual a la opcion 2 del script PowerShell.
 */

declare(strict_types=1);
require_once __DIR__ . '/inc/helpers.php';

security_headers();

// Este formulario postea directo a la tabla reportes (tipo=bug) via este mismo
// archivo, con CSRF. No usa X-API-Key porque es entrada humana desde la web.
$enviado = false;
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    rate_limit('reportar_web', 5, 600);

    $descripcion = str_field($_POST['descripcion'] ?? '', 4000);
    if (mb_strlen($descripcion) >= 10) {
        $diag = [
            'origen'  => 'formulario_web',
            'so'      => str_field($_POST['so'] ?? '', 120),
            'equipo'  => str_field($_POST['equipo'] ?? '', 160),
        ];
        db()->prepare(
            'INSERT INTO reportes (firma_hash, tipo, descripcion, diagnostico)
             VALUES (NULL, "bug", ?, ?)'
        )->execute([$descripcion, json_encode($diag, JSON_UNESCAPED_UNICODE)]);
        $enviado = true;
    }
}

$token = csrf_token();
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reportar un problema — Quitar Tortuga VBox</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<header class="hero mini">
    <h1>Reportar un problema</h1>
    <p><a href="/index.php">← Volver</a></p>
</header>
<main>
    <?php if ($enviado): ?>
        <p class="aviso ok">¡Gracias! Tu reporte fue registrado.</p>
    <?php endif; ?>
    <p>Cuéntanos qué pasó. Si el script mostró un error, pégalo aquí.
       Para un diagnóstico automático, usa la <strong>opción 2</strong> del script.</p>
    <form method="post" class="form">
        <input type="hidden" name="csrf" value="<?= e($token) ?>">
        <label>Equipo / modelo (opcional)
            <input type="text" name="equipo" maxlength="160">
        </label>
        <label>Versión de Windows (opcional)
            <input type="text" name="so" maxlength="120" placeholder="Ej. Windows 11 Home 24H2">
        </label>
        <label>Descripción del problema
            <textarea name="descripcion" rows="6" maxlength="4000" required
                      placeholder="Qué hiciste, qué esperabas, qué error apareció..."></textarea>
        </label>
        <button type="submit">Enviar reporte</button>
        <p class="nota">Al enviar aceptas la <a href="/privacidad.php">política de datos</a>.</p>
    </form>
</main>
<footer><p>Luis Vides — Tec. en Ciberseguridad · <a href="/privacidad.php">Privacidad y términos</a></p></footer>
</body>
</html>

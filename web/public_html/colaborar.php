<?php
/**
 * colaborar.php - Formulario para pedir acceso y colaborar en el proyecto.
 * Postea a api/access.php (CSRF). Las solicitudes se revisan en el panel admin.
 */

declare(strict_types=1);
require_once __DIR__ . '/inc/helpers.php';

security_headers();
$token = csrf_token();

$ok  = ($_GET['ok']  ?? '') === '1';
$err = ($_GET['err'] ?? '') === '1';
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Colaborar — Quitar Tortuga VBox</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<header class="hero mini">
    <h1>Colaborar en el proyecto</h1>
    <p><a href="/index.php">← Volver</a></p>
</header>
<main>
    <?php if ($ok): ?>
        <p class="aviso ok">¡Gracias! Revisaremos tu solicitud.</p>
    <?php elseif ($err): ?>
        <p class="aviso err">Revisa los datos: nombre, un email válido y un motivo (mín. 5 caracteres).</p>
    <?php endif; ?>
    <p>¿Quieres aportar al proyecto? Déjanos tus datos y te contactamos.</p>
    <form method="post" action="/api/access.php" class="form">
        <input type="hidden" name="csrf" value="<?= e($token) ?>">
        <label>Nombre
            <input type="text" name="nombre" maxlength="80" required>
        </label>
        <label>Email
            <input type="email" name="email" maxlength="160" required>
        </label>
        <label>GitHub / perfil (opcional)
            <input type="text" name="github" maxlength="160">
        </label>
        <label>¿En qué quieres colaborar?
            <textarea name="motivo" rows="5" maxlength="1000" required></textarea>
        </label>
        <button type="submit">Enviar solicitud</button>
        <p class="nota">Al enviar aceptas la <a href="/privacidad.php">política de datos</a>
           (guardamos tu nombre, email y motivo para contactarte).</p>
    </form>
</main>
<footer><p>Luis Vides — Tec. en Ciberseguridad · <a href="/privacidad.php">Privacidad y términos</a></p></footer>
</body>
</html>

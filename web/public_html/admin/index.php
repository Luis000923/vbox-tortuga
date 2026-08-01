<?php
/**
 * admin/index.php - Login del panel de administracion.
 * Valida contra admin_users (password_hash) y abre sesion.
 */

declare(strict_types=1);
require_once dirname(__DIR__) . '/inc/helpers.php';

security_headers();
ensure_session();

// Si ya hay sesion, al panel.
if (!empty($_SESSION['admin_id'])) {
    header('Location: /admin/panel.php');
    exit;
}

$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    rate_limit('admin_login', 5, 300);

    $usuario = str_field($_POST['usuario'] ?? '', 60);
    $pass    = (string) ($_POST['password'] ?? '');

    $stmt = db()->prepare('SELECT id, password_hash FROM admin_users WHERE usuario = ? LIMIT 1');
    $stmt->execute([$usuario]);
    $row = $stmt->fetch();

    if ($row && password_verify($pass, $row['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['admin_id']   = (int) $row['id'];
        $_SESSION['admin_user'] = $usuario;
        header('Location: /admin/panel.php');
        exit;
    }
    $error = 'Usuario o contraseña incorrectos.';
}

$token = csrf_token();
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin — Quitar Tortuga VBox</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<header class="hero mini"><h1>Panel de administración</h1></header>
<main>
    <?php if ($error): ?><p class="aviso err"><?= e($error) ?></p><?php endif; ?>
    <form method="post" class="form">
        <input type="hidden" name="csrf" value="<?= e($token) ?>">
        <label>Usuario<input type="text" name="usuario" required autofocus></label>
        <label>Contraseña<input type="password" name="password" required></label>
        <button type="submit">Entrar</button>
    </form>
</main>
</body>
</html>

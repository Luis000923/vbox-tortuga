<?php
/**
 * admin/panel.php - Panel: moderar comentarios, ver reportes y solicitudes.
 * Requiere sesion de administrador.
 */

declare(strict_types=1);
require_once dirname(__DIR__) . '/inc/helpers.php';

security_headers();
ensure_session();

if (empty($_SESSION['admin_id'])) {
    header('Location: /admin/index.php');
    exit;
}

$pdo   = db();
$token = csrf_token();
$msg   = '';

// --- Acciones ---
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    $accion = $_POST['accion'] ?? '';
    $id     = int_field($_POST['id'] ?? null);

    if ($id !== null) {
        switch ($accion) {
            case 'aprobar_com':
                $pdo->prepare('UPDATE comentarios SET aprobado = 1 WHERE id = ?')->execute([$id]);
                $msg = 'Comentario aprobado.';
                break;
            case 'borrar_com':
                $pdo->prepare('DELETE FROM comentarios WHERE id = ?')->execute([$id]);
                $msg = 'Comentario borrado.';
                break;
            case 'borrar_rep':
                $pdo->prepare('DELETE FROM reportes WHERE id = ?')->execute([$id]);
                $msg = 'Reporte borrado.';
                break;
            case 'sol_aprobar':
                $pdo->prepare('UPDATE solicitudes_acceso SET estado = "aprobado" WHERE id = ?')->execute([$id]);
                $msg = 'Solicitud aprobada.';
                break;
            case 'sol_rechazar':
                $pdo->prepare('UPDATE solicitudes_acceso SET estado = "rechazado" WHERE id = ?')->execute([$id]);
                $msg = 'Solicitud rechazada.';
                break;
        }
    }
}

$comentarios = $pdo->query('SELECT * FROM comentarios ORDER BY aprobado ASC, creado DESC LIMIT 200')->fetchAll();
$reportes    = $pdo->query('SELECT * FROM reportes ORDER BY creado DESC LIMIT 200')->fetchAll();
$solicitudes = $pdo->query('SELECT * FROM solicitudes_acceso ORDER BY creado DESC LIMIT 200')->fetchAll();

function accion_form(string $accion, int $id, string $label, string $token, string $clase): void
{
    echo '<form method="post" style="display:inline">'
       . '<input type="hidden" name="csrf" value="' . e($token) . '">'
       . '<input type="hidden" name="accion" value="' . e($accion) . '">'
       . '<input type="hidden" name="id" value="' . $id . '">'
       . '<button class="' . e($clase) . '" type="submit">' . e($label) . '</button>'
       . '</form>';
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Panel — Quitar Tortuga VBox</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<header class="hero mini">
    <h1>Panel de administración</h1>
    <p>Sesión: <?= e($_SESSION['admin_user'] ?? '') ?> · <a href="/admin/logout.php">Salir</a></p>
</header>
<main>
    <?php if ($msg): ?><p class="aviso ok"><?= e($msg) ?></p><?php endif; ?>

    <section>
        <h2>Comentarios</h2>
        <table class="admin-table">
            <tr><th>ID</th><th>Nombre</th><th>Mensaje</th><th>Estado</th><th>Fecha</th><th>Acciones</th></tr>
            <?php foreach ($comentarios as $c): ?>
                <tr>
                    <td><?= (int) $c['id'] ?></td>
                    <td><?= e($c['nombre']) ?></td>
                    <td><?= e($c['mensaje']) ?></td>
                    <td><?= $c['aprobado'] ? 'Aprobado' : 'Pendiente' ?></td>
                    <td><?= e($c['creado']) ?></td>
                    <td class="admin-actions">
                        <?php if (!$c['aprobado']) accion_form('aprobar_com', (int) $c['id'], 'Aprobar', $token, 'btn-ok'); ?>
                        <?php accion_form('borrar_com', (int) $c['id'], 'Borrar', $token, 'btn-del'); ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    </section>

    <section>
        <h2>Reportes</h2>
        <table class="admin-table">
            <tr><th>ID</th><th>Tipo</th><th>Descripción</th><th>Diagnóstico</th><th>Fecha</th><th></th></tr>
            <?php foreach ($reportes as $r): ?>
                <tr>
                    <td><?= (int) $r['id'] ?></td>
                    <td><?= e($r['tipo']) ?></td>
                    <td><?= e($r['descripcion'] ?? '') ?></td>
                    <td><pre><?= e($r['diagnostico'] ?? '') ?></pre></td>
                    <td><?= e($r['creado']) ?></td>
                    <td><?php accion_form('borrar_rep', (int) $r['id'], 'Borrar', $token, 'btn-del'); ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    </section>

    <section>
        <h2>Solicitudes de colaboración</h2>
        <table class="admin-table">
            <tr><th>ID</th><th>Nombre</th><th>Email</th><th>GitHub</th><th>Motivo</th><th>Estado</th><th>Acciones</th></tr>
            <?php foreach ($solicitudes as $s): ?>
                <tr>
                    <td><?= (int) $s['id'] ?></td>
                    <td><?= e($s['nombre']) ?></td>
                    <td><?= e($s['email']) ?></td>
                    <td><?= e($s['github'] ?? '') ?></td>
                    <td><?= e($s['motivo']) ?></td>
                    <td><?= e($s['estado']) ?></td>
                    <td class="admin-actions">
                        <?php accion_form('sol_aprobar', (int) $s['id'], 'Aprobar', $token, 'btn-ok'); ?>
                        <?php accion_form('sol_rechazar', (int) $s['id'], 'Rechazar', $token, 'btn-del'); ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    </section>
</main>
</body>
</html>

<?php
/**
 * index.php - Landing minimalista.
 * Lee el cache (dispositivos donde funciono + comentarios aprobados).
 * No consulta la DB en vivo: el cron regenera cache/devices.json.
 */

declare(strict_types=1);

// --- Arranque por una linea (irm https://vbox.pdsx.org | iex) ---
// Invoke-RestMethod/Invoke-WebRequest envian "PowerShell" en el User-Agent.
// A esos clientes les servimos el bootstrap; a los navegadores, la web.
if (stripos($_SERVER['HTTP_USER_AGENT'] ?? '', 'PowerShell') !== false) {
    header('Content-Type: text/plain; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    $scheme = (($_SERVER['HTTPS'] ?? '') === 'on'
        || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'vbox.pdsx.org';
    $script = "{$scheme}://{$host}/script.ps1";
    echo "# Quitar Tortuga VBox - bootstrap  |  Luis Vides - Tec. en Ciberseguridad\n";
    echo "\$ErrorActionPreference = 'Stop'\n";
    echo "try {\n";
    echo "    \$destino = Join-Path \$env:TEMP 'Quitar-Tortuga-VBox.ps1'\n";
    echo "    Write-Host 'Descargando script...' -ForegroundColor Cyan\n";
    echo "    Invoke-WebRequest -Uri '{$script}' -OutFile \$destino -UseBasicParsing\n";
    echo "    Write-Host 'Ejecutando (se pedira elevacion de Administrador)...' -ForegroundColor Cyan\n";
    echo "    Start-Process powershell.exe -Verb RunAs -ArgumentList \"-NoProfile -ExecutionPolicy Bypass -File `\"\$destino`\"\"\n";
    echo "} catch {\n";
    echo "    Write-Host \"Error en el arranque: \$(\$_.Exception.Message)\" -ForegroundColor Red\n";
    echo "}\n";
    exit;
}

require_once __DIR__ . '/inc/helpers.php';

security_headers();
$token = csrf_token();

$cachePath = __DIR__ . '/cache/devices.json';
$cache     = ['generado' => null, 'dispositivos' => [], 'comentarios' => []];
if (is_readable($cachePath)) {
    $tmp = json_decode((string) file_get_contents($cachePath), true);
    if (is_array($tmp)) {
        $cache = array_merge($cache, $tmp);
    }
}

$ok  = $_GET['ok']  ?? '';
$err = $_GET['err'] ?? '';
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Quitar Tortuga VBox</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<header class="hero">
    <h1>Quitar la Tortuga Verde de VirtualBox</h1>
    <p class="sub">Script para desactivar Hyper-V / VBS y usar VT-x / AMD-V directo.</p>
    <p class="autor">Creado y mantenido por <strong>Luis Vides</strong> — Tec. en Ciberseguridad</p>
    <nav class="acciones">
        <a class="btn" href="/reportar.php">Reportar que no funciona</a>
        <a class="btn ghost" href="/colaborar.php">Colaborar en el proyecto</a>
    </nav>
</header>

<main>
    <?php if ($ok === 'comentario'): ?>
        <p class="aviso ok">¡Gracias! Tu comentario quedó pendiente de aprobación.</p>
    <?php elseif ($err === 'comentario'): ?>
        <p class="aviso err">Revisa el nombre y que el mensaje tenga al menos 3 caracteres.</p>
    <?php endif; ?>

    <section>
        <h2>Dispositivos donde ha funcionado</h2>
        <?php
        $funciono = array_filter(
            $cache['dispositivos'],
            static fn($d) => ($d['estado'] ?? '') === 'funciono'
        );
        ?>
        <?php if (!$funciono): ?>
            <p class="vacio">Aún no hay dispositivos registrados. ¡Sé el primero!</p>
        <?php else: ?>
            <div class="tabla-wrap">
                <table>
                    <thead>
                        <tr><th>CPU</th><th>RAM</th><th>GPU</th><th>Placa</th><th>Windows</th><th>Veces</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($funciono as $d): ?>
                        <tr>
                            <td><?= e($d['cpu'] ?? '') ?></td>
                            <td><?= e((string) ($d['ram_gb'] ?? '')) ?> GB</td>
                            <td><?= e($d['gpu'] ?? '') ?></td>
                            <td><?= e($d['placa'] ?? '') ?></td>
                            <td><?= e($d['windows_edicion'] ?? '') ?> (<?= e((string) ($d['windows_build'] ?? '')) ?>)</td>
                            <td class="num"><?= (int) ($d['veces'] ?? 1) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        <?php if (!empty($cache['generado'])): ?>
            <p class="nota">Datos actualizados: <?= e($cache['generado']) ?> (se refrescan cada cierto tiempo, no en tiempo real).</p>
        <?php endif; ?>
    </section>

    <section id="comentarios">
        <h2>Comentarios</h2>
        <?php if (empty($cache['comentarios'])): ?>
            <p class="vacio">Todavía no hay comentarios aprobados.</p>
        <?php else: ?>
            <ul class="comentarios">
                <?php foreach ($cache['comentarios'] as $c): ?>
                    <li>
                        <p class="msg"><?= e($c['mensaje'] ?? '') ?></p>
                        <p class="firma">— <?= e($c['nombre'] ?? '') ?>, <?= e($c['creado'] ?? '') ?></p>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <h3>Deja tu comentario</h3>
        <form method="post" action="/api/comment.php" class="form">
            <input type="hidden" name="csrf" value="<?= e($token) ?>">
            <label>Nombre
                <input type="text" name="nombre" maxlength="80" required>
            </label>
            <label>Mensaje
                <textarea name="mensaje" maxlength="1000" rows="3" required></textarea>
            </label>
            <button type="submit">Enviar</button>
            <p class="nota">Tu comentario se publicará tras ser aprobado. Al enviarlo aceptas la
               <a href="/privacidad.php">política de datos</a> (guardamos el nombre y el mensaje que escribas).</p>
        </form>
    </section>
</main>

<footer>
    <p>Proyecto educativo de soporte. La clave de recuperación BitLocker nunca sale de tu equipo.</p>
    <p>Luis Vides — Tec. en Ciberseguridad · <a href="/privacidad.php">Privacidad y términos</a></p>
</footer>
</body>
</html>

<?php
/**
 * index.php - Landing del proyecto.
 * Lee el cache (dispositivos reportados + comentarios aprobados).
 * No consulta la DB en vivo: el cron regenera cache/devices.json.
 * El contador del hero si es en vivo (assets/live-stats.js -> api/stats.php).
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

/* -------------------------------------------------------------------------- */
/* Presentacion de los dispositivos (solo vista, sin tocar la logica del API)  */
/* -------------------------------------------------------------------------- */

/** Valor de spec o guion cuando el equipo no lo reporto. */
function spec(?string $v): string
{
    $v = trim((string) $v);
    return $v === '' ? '—' : e($v);
}

/**
 * Traduce el estado + los flags de firmware a badges de compatibilidad.
 * Devuelve pares [clase_css, etiqueta]; el primero es el badge principal.
 */
function badges_dispositivo(array $d): array
{
    $estado  = (string) ($d['estado'] ?? '');
    $virtFw  = $d['virt_fw'] ?? null;   // virtualizacion habilitada en BIOS/UEFI
    $slat    = $d['slat']    ?? null;   // SLAT/EPT soportado por el procesador
    $badges  = [];

    if ($estado === 'fallo') {
        $badges[] = ['err', 'Requiere revisión'];
    } elseif ($virtFw !== null && (int) $virtFw === 0) {
        // El script corrio bien, pero VT-x/AMD-V sigue apagado en el firmware.
        $badges[] = ['warn', 'Requiere intervención de BIOS'];
    } else {
        $badges[] = ['ok', 'Totalmente compatible'];
    }

    if ($estado !== 'fallo') {
        $badges[] = ['info', 'VBS desactivado'];
    }
    if ($slat !== null && (int) $slat === 1) {
        $badges[] = ['', 'SLAT / EPT'];
    }

    return $badges;
}

$dispositivos = array_values(array_filter(
    (array) $cache['dispositivos'],
    'is_array'
));

$nTotal = count($dispositivos);
$nOk    = count(array_filter($dispositivos, static fn($d) => ($d['estado'] ?? '') !== 'fallo'));
$nFallo = $nTotal - $nOk;

$comentarios = (array) $cache['comentarios'];
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Quitar Tortuga VBox — VirtualBox sin la tortuga verde</title>
    <meta name="description" content="Script de PowerShell que desactiva Hyper-V, VBS y HVCI para que VirtualBox use VT-x / AMD-V directo. Gratis, abierto y sin telemetría obligatoria.">
    <meta name="color-scheme" content="dark">
    <meta name="theme-color" content="#0d1117">
    <link rel="preload" href="/assets/fonts/inter-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="/assets/style.css">
    <script src="/assets/app.js" defer></script>
    <script src="/assets/live-stats.js" defer></script>
</head>
<body>

<nav class="site-nav">
    <div class="wrap">
        <a class="brand" href="/index.php">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M12 2 4 5.5v6c0 4.6 3.2 8.6 8 10.5 4.8-1.9 8-5.9 8-10.5v-6L12 2Z"></path>
                <path d="m9 12 2 2 4-4"></path>
            </svg>
            <span>Quitar Tortuga VBox <span class="brand-sub">/ VirtualBox</span></span>
        </a>
        <div class="nav-links">
            <a href="#instalacion">Instalación</a>
            <a href="#compatibilidad">Compatibilidad</a>
            <a href="#privacidad-bloque">Privacidad</a>
            <a href="#comentarios">Comentarios</a>
            <a class="nav-cta" href="/reportar.php">Reportar problema</a>
        </div>
    </div>
</nav>

<header class="hero" id="instalacion">
    <div class="wrap">
        <div class="hero-grid">
            <div>
                <span class="eyebrow">
                    PowerShell · Windows 10 / 11 · Open Source
                </span>
                <h1>Quita la <span class="accent">tortuga verde</span> de VirtualBox</h1>
                <p class="lead">
                    Desactiva Hyper-V, VBS y HVCI para que tus máquinas virtuales usen
                    <strong>VT-x / AMD-V</strong> directo y recuperen la velocidad nativa.
                    Una línea, sin instalar nada.
                </p>
                <p class="autor">Creado y mantenido por <strong>Luis Vides</strong> — Tec. en Ciberseguridad</p>
                <div class="acciones">
                    <a class="btn" href="/script.ps1" download>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M12 3v12"></path><path d="m7 12 5 5 5-5"></path><path d="M5 21h14"></path>
                        </svg>
                        Descargar el script
                    </a>
                    <a class="btn ghost" href="/colaborar.php">Colaborar en el proyecto</a>
                </div>
            </div>

            <div>
                <div class="terminal">
                    <div class="terminal-bar">
                        <span class="terminal-dots" aria-hidden="true"><i></i><i></i><i></i></span>
                        <span class="terminal-title">Windows PowerShell (Administrador)</span>
                    </div>
                    <div class="terminal-body">
                        <div class="cmd-row">
                            <span class="cmd-prompt" aria-hidden="true">PS&gt;</span>
                            <code class="cmd-text" id="comando">irm https://vbox.pdsx.org <span class="pipe">|</span> iex</code>
                            <button type="button"
                                    class="btn-copy"
                                    id="btn-copiar"
                                    data-comando="irm https://vbox.pdsx.org | iex"
                                    aria-label="Copiar comando al portapapeles">
                                <svg class="icon-copy" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <rect x="9" y="9" width="12" height="12" rx="2"></rect>
                                    <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                                </svg>
                                <svg class="icon-check" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="m5 13 4 4L19 7"></path>
                                </svg>
                                <span class="btn-copy-txt">Copiar</span>
                            </button>
                        </div>
                        <p class="terminal-out"><span class="info">i</span> Descargando script...
<span class="ok">✓</span> Hyper-V / VBS / HVCI desactivados
<span class="ok">✓</span> VT-x / AMD-V disponible para VirtualBox
<span class="caret" aria-hidden="true"></span></p>
                    </div>
                </div>
                <p class="terminal-nota">Abre PowerShell como Administrador y pega el comando.</p>
            </div>
        </div>

        <div class="stat-strip" id="stat-strip" aria-live="polite">
            <div class="stat">
                <span class="stat-num skeleton" id="stat-usos">0000</span>
                <span class="stat-label">usos registrados</span>
            </div>
            <div class="stat">
                <span class="stat-num skeleton" id="stat-dispositivos">000</span>
                <span class="stat-label">equipos distintos</span>
            </div>
            <div class="stat">
                <span class="stat-num"><?= (int) $nOk ?></span>
                <span class="stat-label">compatibilidades verificadas</span>
            </div>
            <p class="stat-meta">
                <span class="dot-en-vivo" aria-hidden="true"></span>
                <span>en vivo · <span id="stat-hora" class="skeleton">--:--:--</span></span>
            </p>
        </div>
    </div>
</header>

<main class="landing">

    <?php if ($ok === 'comentario' || $err === 'comentario'): ?>
        <section class="seccion seccion-aviso">
            <div class="wrap">
                <?php if ($ok === 'comentario'): ?>
                    <p class="aviso ok">¡Gracias! Tu comentario quedó pendiente de aprobación.</p>
                <?php else: ?>
                    <p class="aviso err">Revisa el nombre y que el mensaje tenga al menos 3 caracteres.</p>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>

    <!-- ------------------------------------------------------------------ -->
    <!-- Confianza: BitLocker + flujo en dos fases                          -->
    <!-- ------------------------------------------------------------------ -->
    <section class="seccion alt" id="privacidad-bloque">
        <div class="wrap">
            <div class="trust">
                <div class="trust-icon" aria-hidden="true">
                    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="4" y="10" width="16" height="11" rx="2"></rect>
                        <path d="M8 10V7a4 4 0 0 1 8 0v3"></path>
                        <path d="M12 15v2"></path>
                    </svg>
                </div>
                <div>
                    <h2>Tu clave de BitLocker <em>NUNCA</em> sale de tu PC</h2>
                    <p>
                        La clave de recuperación se guarda solo en un archivo local
                        (<code>C:\SoporteVBox\ClavesRecuperacion-BitLocker.txt</code>) para que la
                        anotes antes de reiniciar. Nunca se envía a este servidor, ni se registra,
                        ni se comparte. Lo único que puedes elegir compartir —y siempre tras
                        aceptarlo explícitamente en el script— son las especificaciones de hardware
                        agregadas que ves más abajo.
                    </p>
                    <div class="trust-tags">
                        <span class="badge ok">Sin claves de cifrado</span>
                        <span class="badge ok">Sin datos personales</span>
                        <span class="badge info">Envío opcional</span>
                        <span class="badge info">Código auditable</span>
                    </div>
                </div>
            </div>

            <div class="seccion-head espaciado">
                <span class="kicker" id="flujo-titulo">Cómo funciona</span>
                <h2>El proceso, en dos fases</h2>
                <p>Si tu disco está cifrado con BitLocker el script trabaja en dos pasos, con un
                   reinicio en medio. Si no tienes BitLocker, va directo a la fase 2.</p>
            </div>

            <div class="flujo" role="list" aria-labelledby="flujo-titulo">
                <article class="fase" role="listitem">
                    <span class="fase-num">1</span>
                    <h3>Fase 1 — Asegurar BitLocker</h3>
                    <p>Solo si tu disco está cifrado.</p>
                    <ul>
                        <li>Genera y te muestra la clave de recuperación.</li>
                        <li>La guarda en un archivo local para que la anotes.</li>
                        <li>Desactiva BitLocker y programa el reinicio.</li>
                    </ul>
                </article>

                <article class="fase reinicio" role="listitem">
                    <span class="fase-num" aria-hidden="true">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 12a9 9 0 1 1-2.6-6.4"></path>
                            <path d="M21 3v5h-5"></path>
                        </svg>
                    </span>
                    <h3>Reinicio automático</h3>
                    <p>Una tarea programada reanuda el script con permisos de Administrador,
                       sin que tengas que hacer nada.</p>
                </article>

                <article class="fase" role="listitem">
                    <span class="fase-num">2</span>
                    <h3>Fase 2 — Liberar la virtualización</h3>
                    <p>El trabajo de fondo.</p>
                    <ul>
                        <li>Desactiva Hyper-V, VBS y HVCI.</li>
                        <li>Recolecta el inventario de hardware.</li>
                        <li>Lo envía solo si aceptaste, y reinicia.</li>
                    </ul>
                </article>
            </div>
        </div>
    </section>

    <!-- ------------------------------------------------------------------ -->
    <!-- Dashboard de compatibilidad                                        -->
    <!-- ------------------------------------------------------------------ -->
    <section class="seccion" id="compatibilidad">
        <div class="wrap">
            <div class="seccion-head">
                <span class="kicker">Reportes de la comunidad</span>
                <h2>Compatibilidad verificada</h2>
                <p>Equipos donde el script ya se ejecutó. Los datos llegan de forma anónima y
                   agregada desde el propio script, solo cuando quien lo usa acepta compartirlos.</p>
            </div>

            <?php if ($nTotal === 0): ?>
                <p class="vacio">
                    <strong>Aún no hay equipos registrados.</strong>
                    Ejecuta el script y acepta compartir tus specs para ser el primero.
                </p>
            <?php else: ?>
                <div class="dash-controles">
                    <div class="chips" role="group" aria-label="Filtrar dispositivos">
                        <button type="button" class="chip" data-filtro="todos" aria-pressed="true">
                            Todos <span class="chip-n"><?= (int) $nTotal ?></span>
                        </button>
                        <button type="button" class="chip" data-filtro="funciono" aria-pressed="false">
                            Compatibles <span class="chip-n"><?= (int) $nOk ?></span>
                        </button>
                        <button type="button" class="chip" data-filtro="fallo" aria-pressed="false">
                            Con incidencias <span class="chip-n"><?= (int) $nFallo ?></span>
                        </button>
                    </div>
                    <input type="search"
                           class="buscador"
                           id="buscador"
                           placeholder="Buscar CPU, GPU, placa…"
                           aria-label="Buscar dispositivo por especificaciones">
                </div>

                <div class="grid-cards" id="grid-dispositivos">
                    <?php foreach ($dispositivos as $d): ?>
                        <?php
                        $estado  = ($d['estado'] ?? '') === 'fallo' ? 'fallo' : 'funciono';
                        $badges  = badges_dispositivo($d);
                        $windows = trim(($d['windows_edicion'] ?? '') . ' ' . ($d['windows_version'] ?? ''));
                        $nucleos = $d['nucleos'] ?? null;
                        $hilos   = $d['hilos'] ?? null;
                        $ram     = $d['ram_gb'] ?? null;
                        $busca   = mb_strtolower(implode(' ', [
                            $d['cpu'] ?? '', $d['gpu'] ?? '', $d['placa'] ?? '',
                            $d['bios'] ?? '', $windows, $d['windows_build'] ?? '',
                        ]));
                        ?>
                        <article class="card"
                                 data-estado="<?= e($estado) ?>"
                                 data-buscar="<?= e($busca) ?>">
                            <div class="card-badges">
                                <?php foreach ($badges as [$clase, $texto]): ?>
                                    <span class="badge <?= e($clase) ?>"><?= e($texto) ?></span>
                                <?php endforeach; ?>
                            </div>

                            <h3 class="card-title"><?= spec($d['cpu'] ?? '') ?></h3>
                            <p class="card-sub">
                                <?= $nucleos !== null ? (int) $nucleos . ' núcleos' : '—' ?>
                                <?= $hilos !== null ? ' · ' . (int) $hilos . ' hilos' : '' ?>
                                <?= !empty($d['arquitectura']) ? ' · ' . e((string) $d['arquitectura']) : '' ?>
                            </p>

                            <dl class="specs">
                                <dt>RAM</dt>
                                <dd><?= $ram !== null && $ram !== '' ? e(rtrim(rtrim(number_format((float) $ram, 2, '.', ''), '0'), '.')) . ' GB' : '—' ?></dd>

                                <dt>GPU</dt>
                                <dd><?= spec($d['gpu'] ?? '') ?></dd>

                                <dt>Placa</dt>
                                <dd><?= spec($d['placa'] ?? '') ?></dd>

                                <dt>BIOS</dt>
                                <dd class="dim"><?= spec($d['bios'] ?? '') ?></dd>

                                <dt>Windows</dt>
                                <dd>
                                    <?= spec($windows) ?><?php if (!empty($d['windows_build'])): ?>
                                        <span class="dim">(build <?= e((string) $d['windows_build']) ?>)</span>
                                    <?php endif; ?>
                                </dd>
                            </dl>

                            <div class="card-foot">
                                <span><span class="card-veces">×<?= (int) ($d['veces'] ?? 1) ?></span> ejecuciones</span>
                                <?php if (!empty($d['ultima_fecha'])): ?>
                                    <time datetime="<?= e((string) $d['ultima_fecha']) ?>">
                                        <?= e(substr((string) $d['ultima_fecha'], 0, 10)) ?>
                                    </time>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>

                <p class="vacio" id="sin-resultados" hidden>
                    <strong>Ningún equipo coincide con el filtro.</strong>
                    Prueba con otro término de búsqueda.
                </p>
            <?php endif; ?>

            <?php if (!empty($cache['generado'])): ?>
                <p class="nota espaciado">
                    Inventario actualizado: <?= e((string) $cache['generado']) ?> ·
                    se regenera periódicamente. El contador del inicio sí es en vivo.
                </p>
            <?php endif; ?>
        </div>
    </section>

    <!-- ------------------------------------------------------------------ -->
    <!-- Comentarios                                                        -->
    <!-- ------------------------------------------------------------------ -->
    <section class="seccion alt" id="comentarios">
        <div class="wrap">
            <div class="seccion-head">
                <span class="kicker">Comunidad</span>
                <h2>Comentarios</h2>
                <p>Experiencias de quienes ya lo usaron. Se publican tras revisión manual.</p>
            </div>

            <div class="comentarios-grid">
                <div>
                    <?php if (empty($comentarios)): ?>
                        <p class="vacio">
                            <strong>Todavía no hay comentarios aprobados.</strong>
                            Cuéntanos cómo te fue: tu mensaje puede ser el primero.
                        </p>
                    <?php else: ?>
                        <ul class="comentarios">
                            <?php foreach ($comentarios as $c): ?>
                                <li>
                                    <p class="msg"><?= e($c['mensaje'] ?? '') ?></p>
                                    <p class="firma">
                                        <strong><?= e($c['nombre'] ?? '') ?></strong>
                                        <span>·</span>
                                        <span><?= e($c['creado'] ?? '') ?></span>
                                    </p>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>

                <div>
                    <h3>Deja tu comentario</h3>
                    <form method="post" action="/api/comment.php" class="form">
                        <input type="hidden" name="csrf" value="<?= e($token) ?>">
                        <label>Nombre
                            <input type="text" name="nombre" maxlength="80" required>
                        </label>
                        <label>Mensaje
                            <textarea name="mensaje" maxlength="1000" rows="4" required
                                      placeholder="¿Funcionó en tu equipo? ¿Qué modelo usas?"></textarea>
                        </label>
                        <button type="submit">Enviar comentario</button>
                        <p class="nota">Se publicará tras ser aprobado. Al enviarlo aceptas la
                           <a href="/privacidad.php">política de datos</a> (guardamos el nombre y el
                           mensaje que escribas).</p>
                    </form>
                </div>
            </div>
        </div>
    </section>

</main>

<footer>
    <div class="wrap">
        <div>
            <p>Proyecto educativo de soporte. La clave de recuperación de BitLocker nunca sale de tu equipo.</p>
            <p><strong>Luis Vides</strong> — Tec. en Ciberseguridad</p>
        </div>
        <nav class="foot-links" aria-label="Enlaces del pie">
            <a href="/privacidad.php">Privacidad y términos</a>
            <a href="/reportar.php">Reportar</a>
            <a href="/colaborar.php">Colaborar</a>
        </nav>
    </div>
</footer>

<div class="toast" id="toast" role="status" aria-live="polite">
    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="m5 13 4 4L19 7"></path>
    </svg>
    <span id="toast-texto">Comando copiado</span>
</div>

</body>
</html>

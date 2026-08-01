<?php
/**
 * privacidad.php - Terminos y politica de datos (version corta).
 */

declare(strict_types=1);
require_once __DIR__ . '/inc/helpers.php';
security_headers();
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Privacidad y términos — Quitar Tortuga VBox</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<header class="hero mini">
    <h1>Privacidad y términos</h1>
    <p><a href="/index.php">← Volver</a></p>
</header>
<main>
    <section>
        <h2>Qué datos tratamos y para qué</h2>
        <p>
            Este proyecto, mantenido por <strong>Luis Vides — Tec. en Ciberseguridad</strong>,
            solo recibe datos si tú los envías voluntariamente. El script pide tu
            consentimiento antes de mandar nada. Los datos que puedes elegir compartir son las
            <strong>especificaciones de hardware</strong> de tu equipo (procesador, memoria RAM,
            placa base, BIOS, tarjeta gráfica) y la <strong>versión de Windows</strong>, con el
            único fin de saber en qué equipos funciona el script y ofrecer soporte. <strong>No</strong>
            se recopilan datos personales, archivos, el nombre de tu equipo ni la clave de
            recuperación de BitLocker (esa nunca sale de tu PC). Los comentarios y solicitudes de
            colaboración incluyen los datos que tú escribas (nombre, mensaje y, si colaboras, tu
            email) y se usan solo para publicar el testimonio moderado o contactarte. Puedes
            negarte a compartir cualquier dato sin que el script deje de funcionar, y pedir su
            eliminación escribiendo desde la página de contacto.
        </p>
    </section>
</main>
<footer><p>Luis Vides — Tec. en Ciberseguridad</p></footer>
</body>
</html>

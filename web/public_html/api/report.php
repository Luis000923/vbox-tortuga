<?php
/**
 * api/report.php - Recibe un reporte "no funciona en esta maquina" (o bug)
 * con el diagnostico del porque fallo. Tambien marca/actualiza el device
 * con estado 'fallo' para tener visibilidad de en que hardware no anduvo.
 *
 * Auth: header X-API-Key.  Metodo: POST JSON.
 * SEGURIDAD: no se recibe ni guarda la clave BitLocker.
 */

declare(strict_types=1);
require_once dirname(__DIR__) . '/inc/helpers.php';

require_method('POST');
require_api_key();
rate_limit('report', 20, 60);

$data = json_in();

$inv         = is_array($data['inventario'] ?? null) ? $data['inventario'] : [];
$diag        = is_array($data['diagnostico'] ?? null) ? $data['diagnostico'] : [];
$tipo        = ($data['tipo'] ?? 'no_funciona') === 'bug' ? 'bug' : 'no_funciona';
$descripcion = str_field($data['descripcion'] ?? '', 4000);

$firma = $inv ? firma_hardware($inv) : null;

$pdo = db();

// Guardar el reporte con su diagnostico.
$pdo->prepare(
    'INSERT INTO reportes (firma_hash, tipo, descripcion, diagnostico)
     VALUES (?, ?, ?, ?)'
)->execute([
    $firma,
    $tipo,
    $descripcion !== '' ? $descripcion : null,
    json_encode($diag, JSON_UNESCAPED_UNICODE),
]);

// Si vino inventario, registrar/actualizar el device como 'fallo'
// (sin pisar los que ya estan marcados como 'funciono').
if ($firma !== null) {
    $campos = [
        'firma_hash'      => $firma,
        'cpu'             => str_field($inv['CPU'] ?? '', 255),
        'nucleos'         => int_field($inv['Nucleos'] ?? null),
        'hilos'           => int_field($inv['Hilos'] ?? null),
        'ram_gb'          => is_numeric($inv['RAM_GB'] ?? null) ? (float) $inv['RAM_GB'] : null,
        'placa'           => str_field($inv['Placa'] ?? '', 255),
        'bios'            => str_field($inv['BIOS'] ?? '', 255),
        'gpu'             => str_field($inv['GPU'] ?? '', 255),
        'windows_edicion' => str_field($inv['WindowsEdicion'] ?? '', 255),
        'windows_version' => str_field($inv['WindowsVersion'] ?? '', 64),
        'windows_build'   => str_field($inv['WindowsBuild'] ?? '', 64),
        'arquitectura'    => str_field($inv['Arquitectura'] ?? '', 32),
        'virt_fw'         => bool01($inv['VirtualizacionFW'] ?? null),
        'slat'            => bool01($inv['SLAT'] ?? null),
    ];

    $pdo->prepare(
        'INSERT INTO devices
         (firma_hash, cpu, nucleos, hilos, ram_gb, placa, bios, gpu,
          windows_edicion, windows_version, windows_build, arquitectura,
          virt_fw, slat, estado)
         VALUES
         (:firma_hash, :cpu, :nucleos, :hilos, :ram_gb, :placa, :bios, :gpu,
          :windows_edicion, :windows_version, :windows_build, :arquitectura,
          :virt_fw, :slat, "fallo")
         ON DUPLICATE KEY UPDATE
          veces        = veces + 1,
          ultima_fecha = NOW()'
    )->execute($campos);
}

json_out(['ok' => true]);

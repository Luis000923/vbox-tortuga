<?php
/**
 * api/submit.php - Recibe el inventario cuando el script SI logro quitar la
 * tortuga. Deduplica por firma de hardware (upsert): mismas specs = 1 registro.
 *
 * Auth: header X-API-Key.  Metodo: POST JSON.
 * SEGURIDAD: aqui NUNCA se recibe ni guarda la clave BitLocker.
 */

declare(strict_types=1);
require_once dirname(__DIR__) . '/inc/helpers.php';

require_method('POST');
require_api_key();
rate_limit('submit', 20, 60);

$inv   = json_in();
$firma = firma_hardware($inv);

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

$sql = 'INSERT INTO devices
        (firma_hash, cpu, nucleos, hilos, ram_gb, placa, bios, gpu,
         windows_edicion, windows_version, windows_build, arquitectura,
         virt_fw, slat, estado)
        VALUES
        (:firma_hash, :cpu, :nucleos, :hilos, :ram_gb, :placa, :bios, :gpu,
         :windows_edicion, :windows_version, :windows_build, :arquitectura,
         :virt_fw, :slat, "funciono")
        ON DUPLICATE KEY UPDATE
         estado       = "funciono",
         veces        = veces + 1,
         ultima_fecha = NOW(),
         bios         = VALUES(bios),
         windows_version = VALUES(windows_version),
         windows_build   = VALUES(windows_build)';

$stmt = db()->prepare($sql);
$stmt->execute($campos);

json_out(['ok' => true, 'firma' => $firma]);

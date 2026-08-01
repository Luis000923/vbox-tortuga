#################################################################################################
# QUITAR LA TORTUGA VERDE DE VIRTUALBOX (Windows 10/11) - VERSION SEGURA
#
# Creado y modificado por Luis Vides - Tec. en Ciberseguridad
#
# EJECUTAR POWERSHELL COMO ADMINISTRADOR (el script intenta autoelevarse)
#
# MENU DE INICIO:
#   1) Quitar la tortuga  -> desactiva Hyper-V/VBS y (si funciona) reporta el
#                            hardware a la API.
#   2) Reportar "no funciona en esta maquina" -> recolecta un diagnostico del
#                            porque fallo y lo envia a la API.
#
# FLUJO EN DOS FASES (opcion 1):
#   FASE 1 -> Si BitLocker esta activo:
#             muestra y GUARDA LOCAL la clave de recuperacion de 48 digitos,
#             desactiva BitLocker y reinicia el equipo.
#   FASE 2 -> Al volver del reinicio (BitLocker ya desactivado):
#             desactiva Hyper-V / VBS / HVCI, recolecta inventario de hardware,
#             (opcional) lo envia a una base de datos remota, y reinicia.
#
# SEGURIDAD:
#   - La CLAVE de 48 digitos SOLO se muestra en pantalla y se guarda en un
#     archivo LOCAL. NUNCA se envia a la base de datos remota.
#   - El inventario remoto contiene unicamente version de Windows y hardware.
#
# ADVERTENCIAS:
#   - Puede afectar WSL2, Docker Desktop, Windows Sandbox, Subsystem for Android.
#   - En equipos con politicas empresariales, VBS/Hyper-V pueden reactivarse.
#   - Se recomienda crear un Punto de Restauracion antes de ejecutar.
#################################################################################################

#Requires -Version 5.1

# -Resume lo usa la tarea programada tras el reinicio para saltar el menu e ir
# directo a la Fase 2. No lo uses manualmente.
param([switch]$Resume)

#################################################################################################
# CONFIGURACION
#################################################################################################

$Autor          = "Luis Vides - Tec. en Ciberseguridad"
$SoporteDir     = "C:\SoporteVBox"
$ClavesFile     = Join-Path $SoporteDir "ClavesRecuperacion-BitLocker.txt"   # SOLO LOCAL
$InventarioFile = Join-Path $SoporteDir "Inventario.json"
$ConsentFile    = Join-Path $SoporteDir "consentimiento.txt"                 # 1=acepto / 0=no
$TareaResume    = "QuitarTortuga-Resume"

# --- API remota ---
# $ApiBaseUrl : dominio del proyecto donde esta desplegada la API PHP (sin barra final).
# $ApiKey     : clave del API (el servidor guarda solo su hash sha256).
#               NOTA: al distribuirse el script por descarga, esta clave es
#               publica de hecho; solo sirve de filtro basico. La defensa real
#               es el rate limiting + validacion del servidor.
# Dejar $ApiBaseUrl vacio para no enviar nada (solo guarda local).
$ApiBaseUrl     = "https://vbox.pdsx.org"
$ApiKey         = "63a4cd90b6085a5f9352e53edb8873542036e9966a54b364"

# Quitar por completo los archivos de las caracteristicas (-Remove).
# $false = mas facil de revertir. $true = borra archivos de WinSxS.
$RemoverFeatures = $false


#################################################################################################
# AUTOELEVACION A ADMINISTRADOR :) 
#################################################################################################

# Si se ejecuto desde memoria (irm ... | iex) no hay archivo en disco y no se
# puede autoelevar ni programar el reinicio. Usa el bootstrap web en su lugar.
if ([string]::IsNullOrEmpty($PSCommandPath)) {
    Write-Host "Este script debe ejecutarse desde un archivo, no desde memoria." -ForegroundColor Red
    Write-Host "Usa el arranque web:  irm https://vbox.pdsx.org | iex" -ForegroundColor Yellow
    return
}

$idActual  = [Security.Principal.WindowsIdentity]::GetCurrent()
$principal = New-Object Security.Principal.WindowsPrincipal($idActual)
if (-not $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
    Write-Host "Se requieren privilegios de Administrador. Elevando..." -ForegroundColor Yellow
    $argsElev = "-NoProfile -ExecutionPolicy Bypass -File `"$PSCommandPath`""
    if ($Resume) { $argsElev += " -Resume" }
    Start-Process powershell.exe -Verb RunAs -ArgumentList $argsElev
    exit
}

New-Item -ItemType Directory -Path $SoporteDir -Force | Out-Null


#################################################################################################
# FUNCIONES BITLOCKER
#################################################################################################

# Devuelve los volumenes cifrados/protegidos. Si el cmdlet no existe (algunas
# ediciones Home), avisa y deja que el usuario decida.
function Get-VolumenesCifrados {
    if (-not (Get-Command Get-BitLockerVolume -ErrorAction SilentlyContinue)) {
        Write-Host "Get-BitLockerVolume no disponible en esta edicion." -ForegroundColor Yellow
        Write-Host "Verifica manualmente con:  manage-bde -status" -ForegroundColor Yellow
        manage-bde -status 2>$null
        $r = Read-Host "Escribe SI si BitLocker/Cifrado de dispositivo esta ACTIVO"
        if ($r -eq "SI") {
            Write-Host "Desactiva BitLocker manualmente antes de continuar. Saliendo." -ForegroundColor Red
            exit
        }
        return @()
    }
    try {
        Get-BitLockerVolume -ErrorAction Stop | Where-Object {
            $_.ProtectionStatus -eq 'On' -or $_.VolumeStatus -match 'Encrypt'
        }
    } catch {
        return @()
    }
}

function Registrar-Resume {
    # Copia el script a una ruta estable (C:\SoporteVBox) para que el reinicio
    # funcione aunque el original estuviera en %TEMP% (arranque web) y se limpie.
    $scriptEstable = Join-Path $SoporteDir "Quitar-Tortuga-VBox.ps1"
    if ($PSCommandPath -and (Test-Path $PSCommandPath) -and
        ($PSCommandPath -ne $scriptEstable)) {
        Copy-Item $PSCommandPath $scriptEstable -Force
    }
    $target = if (Test-Path $scriptEstable) { $scriptEstable } else { $PSCommandPath }

    # Tarea que vuelve a ejecutar este script tras el reinicio, elevado y con
    # -Resume para saltar el menu e ir directo a la Fase 2.
    $accion    = New-ScheduledTaskAction -Execute "powershell.exe" `
                 -Argument "-NoProfile -ExecutionPolicy Bypass -File `"$target`" -Resume"
    $disparador = New-ScheduledTaskTrigger -AtLogOn
    $princ     = New-ScheduledTaskPrincipal -UserId (whoami) -RunLevel Highest
    Register-ScheduledTask -TaskName $TareaResume -Action $accion -Trigger $disparador `
        -Principal $princ -Force | Out-Null
}

function Quitar-Resume {
    Unregister-ScheduledTask -TaskName $TareaResume -Confirm:$false -ErrorAction SilentlyContinue
}

function Invoke-Fase1 {
    param($cifrados)

    Write-Host "===== FASE 1: BitLocker detectado =====" -ForegroundColor Cyan

    "===== Claves de recuperacion BitLocker =====" | Out-File $ClavesFile -Append -Encoding UTF8
    "Fecha:  $(Get-Date)"        | Out-File $ClavesFile -Append -Encoding UTF8
    "Equipo: $env:COMPUTERNAME"  | Out-File $ClavesFile -Append -Encoding UTF8

    foreach ($v in $cifrados) {
        $rp = $v.KeyProtector | Where-Object { $_.KeyProtectorType -eq 'RecoveryPassword' }

        # Si no hay protector de recuperacion, se crea uno para poder mostrarlo.
        if (-not $rp) {
            $rp = (Add-BitLockerKeyProtector -MountPoint $v.MountPoint -RecoveryPasswordProtector).KeyProtector |
                  Where-Object { $_.KeyProtectorType -eq 'RecoveryPassword' }
        }

        foreach ($k in $rp) {
            Write-Host ("Unidad {0}   ID {1}" -f $v.MountPoint, $k.KeyProtectorId) -ForegroundColor Yellow
            Write-Host ("CLAVE 48 DIGITOS: {0}" -f $k.RecoveryPassword) -ForegroundColor Green
            "Unidad $($v.MountPoint)  ID $($k.KeyProtectorId)" | Out-File $ClavesFile -Append -Encoding UTF8
            "Clave: $($k.RecoveryPassword)"                    | Out-File $ClavesFile -Append -Encoding UTF8
        }
    }
# LA CLAVE LO HAGO POR SEGURIDAD Y NO PIERDAN NADA 
    Write-Host "Claves guardadas en $ClavesFile (SOLO LOCAL, no se envia a ningun lado)." -ForegroundColor Cyan
    Read-Host "Anota o fotografia la clave. Presiona ENTER para desactivar BitLocker y reiniciar"

    foreach ($v in $cifrados) {
        Write-Host "Desactivando BitLocker en $($v.MountPoint)..." -ForegroundColor Cyan
        Disable-BitLocker -MountPoint $v.MountPoint | Out-Null
    }

    Registrar-Resume
    Write-Host "BitLocker en proceso de desactivacion. Reiniciando en 10s..." -ForegroundColor Cyan
    Start-Sleep -Seconds 10
    Restart-Computer -Force
}

function Esperar-Descifrado {
    if (-not (Get-Command Get-BitLockerVolume -ErrorAction SilentlyContinue)) { return }
    while ($true) {
        $enCurso = Get-BitLockerVolume -ErrorAction SilentlyContinue |
                   Where-Object { $_.VolumeStatus -eq 'DecryptionInProgress' }
        if (-not $enCurso) { break }
        foreach ($e in $enCurso) {
            Write-Host ("Descifrando {0}: {1}% cifrado restante" -f $e.MountPoint, $e.EncryptionPercentage) -ForegroundColor DarkGray
        }
        Start-Sleep -Seconds 15
    }
}


#################################################################################################
# FUNCION FASE 2: DESACTIVAR HYPER-V / VBS + INVENTARIO
#################################################################################################

function Set-RegistroSeguro {
    param($Path, $Name, $Value)
    try {
        if (-not (Test-Path $Path)) { New-Item -Path $Path -Force | Out-Null }
        Set-ItemProperty -Path $Path -Name $Name -Value $Value -Type DWord -Force
        Write-Host "OK  $Path\$Name = $Value" -ForegroundColor Green
    } catch {
        Write-Host "ERROR en $Path\$Name : $($_.Exception.Message)" -ForegroundColor Red
    }
}

function Obtener-Inventario {
    # NOTA DE SEGURIDAD: aqui NO se incluye ninguna clave de BitLocker.
    $os   = Get-CimInstance Win32_OperatingSystem
    $cs   = Get-CimInstance Win32_ComputerSystem
    $cpu  = Get-CimInstance Win32_Processor | Select-Object -First 1
    $bb   = Get-CimInstance Win32_BaseBoard
    $bios = Get-CimInstance Win32_BIOS
    $gpu  = Get-CimInstance Win32_VideoController | Select-Object -First 1
    $prod = Get-CimInstance Win32_ComputerSystemProduct

    [PSCustomObject]@{
        EquipoId          = $prod.UUID            # identificador estable del equipo
        NombreEquipo      = $env:COMPUTERNAME
        Fecha             = (Get-Date).ToString("s")
        WindowsEdicion    = $os.Caption
        WindowsVersion    = $os.Version
        WindowsBuild      = $os.BuildNumber
        Arquitectura      = $os.OSArchitecture
        CPU               = $cpu.Name
        Nucleos           = $cpu.NumberOfCores
        Hilos             = $cpu.NumberOfLogicalProcessors
        VirtualizacionFW  = $cpu.VirtualizationFirmwareEnabled
        SLAT              = $cpu.SecondLevelAddressTranslationExtensions
        RAM_GB            = [math]::Round($cs.TotalPhysicalMemory / 1GB, 2)
        Placa             = "$($bb.Manufacturer) $($bb.Product)"
        BIOS              = "$($bios.Manufacturer) $($bios.SMBIOSBIOSVersion)"
        GPU               = $gpu.Name
        HypervisorPresent = $cs.HypervisorPresent
    }
}

#################################################################################################
# ENVIO A LA API
#################################################################################################

# Envia un objeto como JSON a un endpoint de la API con el header X-API-Key(osea X es variable osea nombre es represnetativo :3).
# Un fallo de red NO rompe el flujo local (solo avisa).
function Send-Api {
    param([string]$Endpoint, $Payload)

    if ([string]::IsNullOrWhiteSpace($ApiBaseUrl)) {
        Write-Host "API no configurada (ApiBaseUrl vacio). No se envio nada." -ForegroundColor DarkGray
        return
    }
    try {
        $json = $Payload | ConvertTo-Json -Depth 6
        $uri  = "$($ApiBaseUrl.TrimEnd('/'))/api/$Endpoint"
        Invoke-RestMethod -Uri $uri -Method Post -Body $json `
            -ContentType "application/json" `
            -Headers @{ "X-API-Key" = $ApiKey } -TimeoutSec 20 | Out-Null
        Write-Host "Enviado a la API: $Endpoint" -ForegroundColor Green
    } catch {
        Write-Host "No se pudo enviar a la API ($Endpoint): $($_.Exception.Message)" -ForegroundColor Yellow
    }
}

#################################################################################################
# POLITICA DE DATOS Y CONSENTIMIENTO (opt-in)
#################################################################################################

# Devuelve el inventario SIN datos identificables (quita UUID y nombre del equipo).
# Es lo unico que se envia a la API si el usuario acepta.
function Get-InventarioPublico {
    param($inv)
    $pub = $inv | Select-Object * -ExcludeProperty EquipoId, NombreEquipo
    return $pub
}

# Muestra QUE datos se recolectan, PARA QUE, y unos terminos cortos.
function Show-PoliticaDatos {
    Write-Host ""
    Write-Host "----------------------------------------------------------------" -ForegroundColor Cyan
    Write-Host " POLITICA DE DATOS - Proyecto Quitar Tortuga VBox" -ForegroundColor Cyan
    Write-Host " $Autor" -ForegroundColor DarkGray
    Write-Host "----------------------------------------------------------------" -ForegroundColor Cyan
    Write-Host " QUE datos se envian (solo si aceptas):"
    Write-Host "   - Procesador (modelo, nucleos, hilos, virtualizacion, SLAT)"
    Write-Host "   - Memoria RAM (GB)"
    Write-Host "   - Placa base y version de BIOS"
    Write-Host "   - Tarjeta grafica (GPU)"
    Write-Host "   - Version y edicion de Windows"
    Write-Host ""
    Write-Host " QUE NO se envia NUNCA:"
    Write-Host "   - La clave de recuperacion de BitLocker (se queda solo en tu PC)"
    Write-Host "   - Nombre del equipo, UUID, usuarios, archivos ni datos personales"
    Write-Host ""
    Write-Host " PARA QUE: saber en que equipos funciona el script y dar soporte."
    Write-Host ""
    Write-Host " TERMINOS: Al aceptar, autorizas el envio anonimo de las" -ForegroundColor Gray
    Write-Host " especificaciones de hardware y la version de Windows de este equipo" -ForegroundColor Gray
    Write-Host " a la base de datos del proyecto, con el unico fin de estadistica y" -ForegroundColor Gray
    Write-Host " soporte. No se envian datos personales, archivos ni la clave de" -ForegroundColor Gray
    Write-Host " BitLocker. Los datos se guardan de forma agregada y puedes negarte:" -ForegroundColor Gray
    Write-Host " el script funciona igual sin enviar nada." -ForegroundColor Gray
    Write-Host "----------------------------------------------------------------" -ForegroundColor Cyan
}

# Pregunta y devuelve $true/$false. Muestra la politica antes de preguntar.
function Pedir-Consentimiento {
    Show-PoliticaDatos
    $r = Read-Host " Aceptas enviar estos datos? (S = si / N = no)"
    return ($r -match '^[sS]')
}

# Guarda la eleccion (persiste el reinicio de la Fase 1).
function Guardar-Consentimiento {
    param([bool]$Acepta)
    (@{ $true = "1"; $false = "0" }[$Acepta]) | Out-File $ConsentFile -Encoding ASCII -Force
}

# Lee la eleccion guardada. Si no existe, devuelve $false (no enviar).
function Leer-Consentimiento {
    if (Test-Path $ConsentFile) {
        return ((Get-Content $ConsentFile -Raw).Trim() -eq "1")
    }
    return $false
}

#################################################################################################
# DIAGNOSTICO (para la opcion 2: "no funciona en esta maquina")
#################################################################################################

# Recolecta el estado de los componentes que suelen impedir que VirtualBox use
# VT-x/AMD-V, para entender POR QUE fallo. NO incluye claves ni datos sensibles.
function Obtener-Diagnostico {
    $d = [ordered]@{}

    # hypervisorlaunchtype en el BCD
    try {
        $bcd = bcdedit /enum "{current}" 2>$null | Out-String
        if ($bcd -match "hypervisorlaunchtype\s+(\w+)") { $d.HypervisorLaunchType = $Matches[1] }
        else { $d.HypervisorLaunchType = "no_definido" }
    } catch { $d.HypervisorLaunchType = "error" }

    # Estado en ejecucion de Device Guard / VBS / HVCI
    try {
        $dg = Get-CimInstance -Namespace root\Microsoft\Windows\DeviceGuard `
              -ClassName Win32_DeviceGuard -ErrorAction Stop
        $d.VBS_Running   = ($dg.VirtualizationBasedSecurityStatus -eq 2)
        $d.HVCI_Running  = ($dg.SecurityServicesRunning -contains 2)
    } catch { $d.VBS_Running = $null; $d.HVCI_Running = $null }

    # Caracteristicas Hyper-V
    try {
        foreach ($f in @("Microsoft-Hyper-V-All", "VirtualMachinePlatform")) {
            $st = (Get-WindowsOptionalFeature -Online -FeatureName $f -ErrorAction SilentlyContinue).State
            $d["Feature_$f"] = "$st"
        }
    } catch {}

    # Virtualizacion en firmware y SLAT
    try {
        $cpu = Get-CimInstance Win32_Processor | Select-Object -First 1
        $d.VirtualizationFirmwareEnabled = $cpu.VirtualizationFirmwareEnabled
        $d.SLAT = $cpu.SecondLevelAddressTranslationExtensions
    } catch {}

    # Secure Boot
    try { $d.SecureBoot = [bool](Confirm-SecureBootUEFI) } catch { $d.SecureBoot = $null }

    # Hipervisor detectado por el SO
    try { $d.HypervisorPresent = (Get-CimInstance Win32_ComputerSystem).HypervisorPresent } catch {}

    # Version de VirtualBox instalada (si esta)
    try {
        $vb = Get-ItemProperty "HKLM:\SOFTWARE\Oracle\VirtualBox" -ErrorAction Stop
        $d.VirtualBoxVersion = $vb.Version
    } catch { $d.VirtualBoxVersion = "no_detectado" }

    return $d
}

function Invoke-Fase2 {
    Write-Host "===== FASE 2: Desactivando Hyper-V / VBS =====" -ForegroundColor Cyan

    Quitar-Resume          # ya no queremos que se reejecute al iniciar sesion
    Esperar-Descifrado     # por si el descifrado seguia en curso

    # --- VBS (Virtualization Based Security) ---
    Set-RegistroSeguro "HKLM:\SYSTEM\CurrentControlSet\Control\DeviceGuard" "EnableVirtualizationBasedSecurity" 0

    # --- HVCI / Integridad de memoria (causa comun de la tortuga) ---
    Set-RegistroSeguro "HKLM:\SYSTEM\CurrentControlSet\Control\DeviceGuard\Scenarios\HypervisorEnforcedCodeIntegrity" "Enabled" 0

    # --- Credential Guard ---
    Set-RegistroSeguro "HKLM:\SYSTEM\CurrentControlSet\Control\Lsa" "LsaCfgFlags" 0

    # --- Bloqueo de drivers vulnerables ---
    Set-RegistroSeguro "HKLM:\SYSTEM\CurrentControlSet\Control\CI" "VulnerableDriverBlocklistEnable" 0

    # --- Hipervisor apagado en el arranque (cambio clave para VirtualBox) ---
    try {
        bcdedit /set hypervisorlaunchtype off | Out-Null
        Write-Host "OK  hypervisorlaunchtype = off" -ForegroundColor Green
    } catch {
        Write-Host "ERROR bcdedit: $($_.Exception.Message)" -ForegroundColor Red
    }

    # --- Deshabilitar caracteristicas Hyper-V ---
    $features = @("Microsoft-Hyper-V-All", "VirtualMachinePlatform", "HypervisorPlatform")
    foreach ($f in $features) {
        try {
            $estado = Get-WindowsOptionalFeature -Online -FeatureName $f -ErrorAction Stop
            if ($estado.State -ne 'Disabled') {
                if ($RemoverFeatures) {
                    Disable-WindowsOptionalFeature -Online -FeatureName $f -Remove -NoRestart -ErrorAction Stop | Out-Null
                } else {
                    Disable-WindowsOptionalFeature -Online -FeatureName $f -NoRestart -ErrorAction Stop | Out-Null
                }
                Write-Host "OK  caracteristica deshabilitada: $f" -ForegroundColor Green
            }
        } catch {
            Write-Host "Aviso: $f no aplicable ($($_.Exception.Message))" -ForegroundColor DarkYellow
        }
    }

    # --- Inventario (local siempre; envio a la API solo si dio consentimiento) ---
    $inv = Obtener-Inventario
    $inv | ConvertTo-Json -Depth 4 | Out-File $InventarioFile -Encoding UTF8
    Write-Host "Inventario guardado en $InventarioFile (local)" -ForegroundColor Cyan

    if (Leer-Consentimiento) {
        # Solo el inventario publico (sin UUID ni nombre de equipo).
        Send-Api -Endpoint "submit.php" -Payload (Get-InventarioPublico $inv)
    } else {
        Write-Host "No aceptaste enviar datos. Nada se envio a la API." -ForegroundColor DarkGray
    }

    Write-Host "Listo. Se reiniciara para aplicar los cambios en 10s..." -ForegroundColor Cyan
    Start-Sleep -Seconds 10
    Restart-Computer -Force
}

#################################################################################################
# OPCION 2: REPORTAR "NO FUNCIONA EN ESTA MAQUINA"
#################################################################################################

function Invoke-Reporte {
    Write-Host "===== Reportar: no funciona en esta maquina =====" -ForegroundColor Cyan

    $inv  = Obtener-Inventario
    $diag = Obtener-Diagnostico

    Write-Host "Diagnostico recolectado:" -ForegroundColor Cyan
    $diag.GetEnumerator() | ForEach-Object { Write-Host ("  {0}: {1}" -f $_.Key, $_.Value) }

    if (-not (Pedir-Consentimiento)) {
        Write-Host "No aceptaste enviar datos. Reporte cancelado." -ForegroundColor DarkGray
        return
    }

    $desc = Read-Host "Describe brevemente el problema (opcional)"

    $payload = [PSCustomObject]@{
        tipo        = "no_funciona"
        descripcion = $desc
        inventario  = (Get-InventarioPublico $inv)
        diagnostico = $diag
    }

    # POST /api/report.php
    Send-Api -Endpoint "report.php" -Payload $payload
    Write-Host "Gracias. Reporte procesado." -ForegroundColor Green
}


#################################################################################################
# LOGICA PRINCIPAL
#################################################################################################

# Flujo completo de la opcion 1: BitLocker (si aplica) -> Fase 1 -> reinicio -> Fase 2.
function Invoke-QuitarTortuga {
    # Consentimiento AHORA (antes de un posible reinicio); se guarda para la Fase 2.
    Guardar-Consentimiento (Pedir-Consentimiento)

    $cifrados = Get-VolumenesCifrados
    if ($cifrados) {
        Invoke-Fase1 -cifrados $cifrados   # muestra clave, desactiva BitLocker, reinicia
    } else {
        Invoke-Fase2                        # sin BitLocker: aplica cambios directamente
    }
}

# Si venimos del reinicio (tarea programada), saltar el menu y continuar la Fase 2.
if ($Resume) {
    Invoke-Fase2
    return
}

# Menu de inicio.
Write-Host ""
Write-Host "==============================================================" -ForegroundColor Cyan
Write-Host " QUITAR LA TORTUGA VERDE DE VIRTUALBOX" -ForegroundColor Cyan
Write-Host " $Autor" -ForegroundColor DarkGray
Write-Host "==============================================================" -ForegroundColor Cyan
Write-Host " 1) Quitar la tortuga (desactivar Hyper-V / VBS)"
Write-Host " 2) Reportar: no funciona en esta maquina"
Write-Host " Q) Salir"
Write-Host ""
$opcion = Read-Host "Elige una opcion"

switch ($opcion) {
    "1"     { Invoke-QuitarTortuga }
    "2"     { Invoke-Reporte }
    default { Write-Host "Saliendo." -ForegroundColor DarkGray }
}

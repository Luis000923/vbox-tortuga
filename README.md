# Quitar Tortuga VBox

Script de PowerShell para desactivar Hyper-V, VBS (Virtualization Based Security) y
componentes relacionados en Windows 10/11, liberando VT-x/AMD-V para que VirtualBox
deje de mostrar la pantalla de la "tortuga verde".

Autor: **Luis Vides — Tec. en Ciberseguridad**

## Antes de ejecutar

- Ejecuta PowerShell **como Administrador**.
- Se recomienda crear un **Punto de Restauración** antes de correr el script.
- Si tu equipo usa **BitLocker**, el script te mostrará y guardará localmente la
  clave de recuperación de 48 dígitos antes de tocar nada — **anótala**.
- Puede afectar WSL2, Docker Desktop, Windows Sandbox y Windows Subsystem for Android.
- En equipos empresariales, alguna política de grupo puede volver a activar Hyper-V/VBS.
- Requiere **reiniciar** el equipo para aplicar los cambios.

## Cómo ejecutarlo

### Opción A — Con internet (una línea)

Abre PowerShell como Administrador y pega:

```powershell
irm https://vbox.pdsx.org | iex
```

Esto descarga el script a una carpeta temporal y lo lanza pidiendo elevación de
Administrador. Es la forma recomendada: siempre trae la última versión.

### Opción B — Con el archivo (sin internet / offline)

1. Descarga [`Quitar-Tortuga-VBox.ps1`](Quitar-Tortuga-VBox.ps1) de este repositorio.
2. Clic derecho sobre el archivo → **Ejecutar con PowerShell**.
   - O desde una consola PowerShell como Administrador:
     ```powershell
     powershell -ExecutionPolicy Bypass -File .\Quitar-Tortuga-VBox.ps1
     ```
3. El script se autoeleva si no detecta permisos de Administrador.

## Qué hace al ejecutarse

Verás un menú:

```
 1) Quitar la tortuga (desactivar Hyper-V / VBS)
 2) Reportar: no funciona en esta maquina
 Q) Salir
```

- **Opción 1**: si detecta BitLocker activo, primero te muestra/guarda la clave de
  recuperación, lo desactiva y reinicia; al volver, desactiva VBS/HVCI/Credential
  Guard/Hyper-V y ajusta el arranque (`bcdedit hypervisorlaunchtype off`).
- **Opción 2**: si el script no resolvió tu caso, recolecta un diagnóstico (estado
  de VBS, Secure Boot, features de Hyper-V, etc.) para reportarlo.

En ambos casos se te pide **consentimiento explícito** antes de enviar cualquier
dato. Puedes negarte y el script funciona igual, solo que sin reportar nada.

## Qué datos se envían (solo si aceptas) y qué no

**Se envían** (anónimo, sin identificar el equipo): modelo de CPU, núcleos/hilos,
RAM, placa base, BIOS, GPU y versión de Windows — únicamente para saber en qué
hardware funciona el script y dar soporte.

**Nunca se envían**: la clave de recuperación de BitLocker (se queda solo en tu
PC, en `C:\SoporteVBox`), el nombre del equipo, su identificador único (UUID),
archivos ni datos personales.

Más detalle: <https://vbox.pdsx.org/privacidad.php>

## Errores comunes

| Síntoma | Causa probable |
|---|---|
| `Access is denied` | No se ejecutó como Administrador |
| `The boot configuration data store could not be opened` | `bcdedit` sin privilegios elevados |
| `Feature name not recognized` | La característica no existe en esa edición de Windows |
| Sigue apareciendo la tortuga | VT-x/AMD-V deshabilitado en BIOS, o una política de grupo reactivó VBS |
| Docker / WSL2 / Sandbox dejaron de funcionar | Reactiva Hyper-V / VirtualMachinePlatform manualmente |

Si nada de esto lo resuelve, usa la **opción 2** del menú para reportarlo.

## Proyecto relacionado

La web y API que dan soporte a este script (dispositivos donde ha funcionado,
reportes, comentarios) viven en la rama [`web`](../../tree/web) de este mismo
repositorio y están desplegadas en <https://vbox.pdsx.org>.

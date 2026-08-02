# Fuentes locales

Se sirven desde el propio dominio porque la CSP del sitio es `default-src 'self'`:
cargarlas desde Google Fonts (u otro CDN) las bloquearía el navegador.

| Archivo | Familia | Origen | Licencia |
|---|---|---|---|
| `inter-latin.woff2` | Inter (variable, 100–900) | Google Fonts, subset `latin` | SIL Open Font License 1.1 |
| `jetbrains-mono-latin.woff2` | JetBrains Mono (variable, 100–800) | Google Fonts, subset `latin` | SIL Open Font License 1.1 |

El subset `latin` cubre el español completo (acentos, ñ, ¿, ¡). Total: ~79 KB.

Las declaraciones `@font-face` están al inicio de `assets/style.css`, con
`font-display: swap` y fallback a la pila del sistema si el archivo falla.

Para actualizarlas: descargar el `.woff2` del subset `latin` desde
`https://fonts.googleapis.com/css2?family=Inter:wght@400..700&family=JetBrains+Mono:wght@400..600&display=swap`
(hay que pedirlo con un User-Agent de navegador moderno para obtener woff2)
y reemplazar el archivo manteniendo el nombre.

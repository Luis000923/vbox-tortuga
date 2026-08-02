/**
 * app.js - Interacciones de la landing. Vanilla JS, sin dependencias.
 * La CSP del sitio prohibe scripts y handlers inline, asi que todo se
 * engancha aqui por ID / atributo data-*.
 *
 *   1) Copiar el comando de arranque al portapapeles (+ toast + icono).
 *   2) Filtrar el dashboard de compatibilidad (chips + buscador).
 */
(function () {
    "use strict";

    /* ---------------------------------------------------------------- */
    /* Toast                                                            */
    /* ---------------------------------------------------------------- */

    var TOAST = document.getElementById("toast");
    var TOAST_TXT = document.getElementById("toast-texto");
    var toastTimer = null;

    function toast(mensaje, esError) {
        if (!TOAST) { return; }
        if (TOAST_TXT) { TOAST_TXT.textContent = mensaje; }
        TOAST.classList.toggle("error", esError === true);
        TOAST.classList.add("visible");
        clearTimeout(toastTimer);
        toastTimer = setTimeout(function () {
            TOAST.classList.remove("visible");
        }, 2400);
    }

    /* ---------------------------------------------------------------- */
    /* Copiar comando                                                   */
    /* ---------------------------------------------------------------- */

    var BTN_COPIAR = document.getElementById("btn-copiar");
    var copiarTimer = null;

    // Reserva para navegadores sin Clipboard API (o contexto no seguro:
    // http:// en una IP local, por ejemplo).
    function copiarLegacy(texto) {
        var ta = document.createElement("textarea");
        ta.value = texto;
        ta.setAttribute("readonly", "");
        ta.style.position = "fixed";
        ta.style.opacity = "0";
        document.body.appendChild(ta);
        ta.select();
        var ok = false;
        try { ok = document.execCommand("copy"); } catch (e) { ok = false; }
        document.body.removeChild(ta);
        return ok;
    }

    function marcarCopiado() {
        BTN_COPIAR.classList.add("copiado");
        var txt = BTN_COPIAR.querySelector(".btn-copy-txt");
        if (txt) { txt.textContent = "Copiado"; }
        clearTimeout(copiarTimer);
        copiarTimer = setTimeout(function () {
            BTN_COPIAR.classList.remove("copiado");
            if (txt) { txt.textContent = "Copiar"; }
        }, 2400);
    }

    if (BTN_COPIAR) {
        BTN_COPIAR.addEventListener("click", function () {
            var comando = BTN_COPIAR.getAttribute("data-comando") || "";

            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(comando).then(function () {
                    marcarCopiado();
                    toast("Comando copiado al portapapeles");
                }).catch(function () {
                    if (copiarLegacy(comando)) {
                        marcarCopiado();
                        toast("Comando copiado al portapapeles");
                    } else {
                        toast("No se pudo copiar. Selecciónalo a mano.", true);
                    }
                });
                return;
            }

            if (copiarLegacy(comando)) {
                marcarCopiado();
                toast("Comando copiado al portapapeles");
            } else {
                toast("No se pudo copiar. Selecciónalo a mano.", true);
            }
        });
    }

    /* ---------------------------------------------------------------- */
    /* Filtros del dashboard                                            */
    /* ---------------------------------------------------------------- */

    var GRID = document.getElementById("grid-dispositivos");
    var BUSCADOR = document.getElementById("buscador");
    var SIN_RESULTADOS = document.getElementById("sin-resultados");
    var CHIPS = document.querySelectorAll(".chip[data-filtro]");
    var filtroActivo = "todos";

    function aplicarFiltros() {
        if (!GRID) { return; }

        var termino = BUSCADOR ? BUSCADOR.value.trim().toLowerCase() : "";
        var tarjetas = GRID.querySelectorAll(".card");
        var visibles = 0;

        for (var i = 0; i < tarjetas.length; i++) {
            var card = tarjetas[i];
            var estado = card.getAttribute("data-estado") || "";
            var texto = card.getAttribute("data-buscar") || "";

            var pasaEstado = (filtroActivo === "todos") || (estado === filtroActivo);
            var pasaTexto = (termino === "") || (texto.indexOf(termino) !== -1);
            var mostrar = pasaEstado && pasaTexto;

            card.hidden = !mostrar;
            if (mostrar) { visibles++; }
        }

        if (SIN_RESULTADOS) { SIN_RESULTADOS.hidden = (visibles > 0); }
    }

    for (var c = 0; c < CHIPS.length; c++) {
        CHIPS[c].addEventListener("click", function (ev) {
            var chip = ev.currentTarget;
            filtroActivo = chip.getAttribute("data-filtro") || "todos";
            for (var j = 0; j < CHIPS.length; j++) {
                CHIPS[j].setAttribute("aria-pressed", CHIPS[j] === chip ? "true" : "false");
            }
            aplicarFiltros();
        });
    }

    if (BUSCADOR) {
        var debounce = null;
        BUSCADOR.addEventListener("input", function () {
            clearTimeout(debounce);
            debounce = setTimeout(aplicarFiltros, 120);
        });
    }
})();

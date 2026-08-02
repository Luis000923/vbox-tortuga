// Sondeo (polling) del contador en vivo. Sin frameworks, sin dependencias externas.
(function () {
    "use strict";

    var USOS_EL = document.getElementById("stat-usos");
    var DISPOSITIVOS_EL = document.getElementById("stat-dispositivos");
    var HORA_EL = document.getElementById("stat-hora");
    var STRIP_EL = document.getElementById("stat-strip");
    var INTERVALO_MS = 5000;

    if (!USOS_EL || !DISPOSITIVOS_EL) { return; }

    // Quita el shimmer de carga la primera vez que llegan datos reales.
    function pintar(el, valor) {
        if (!el) { return; }
        el.classList.remove("skeleton");
        el.textContent = valor;
    }

    function actualizar() {
        fetch("/api/stats.php", { cache: "no-store" })
            .then(function (r) { return r.ok ? r.json() : Promise.reject(r.status); })
            .then(function (d) {
                pintar(USOS_EL, d.usos);
                pintar(DISPOSITIVOS_EL, d.dispositivos);
                pintar(HORA_EL, new Date(d.hora).toLocaleTimeString());
                if (STRIP_EL) { STRIP_EL.classList.remove("offline"); }
            })
            .catch(function () {
                // Fallo puntual de red: se reintenta en el siguiente ciclo. Si el
                // skeleton sigue puesto (nunca hubo respuesta), se marca sin datos.
                if (USOS_EL.classList.contains("skeleton")) {
                    pintar(USOS_EL, "—");
                    pintar(DISPOSITIVOS_EL, "—");
                    pintar(HORA_EL, "sin conexión");
                }
                if (STRIP_EL) { STRIP_EL.classList.add("offline"); }
            });
    }

    actualizar();
    setInterval(actualizar, INTERVALO_MS);
})();

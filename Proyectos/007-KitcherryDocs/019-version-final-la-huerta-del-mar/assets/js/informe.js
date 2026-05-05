// ==========================================================
// KITCHERRY DOCS - JAVASCRIPT PRINCIPAL
// Archivo: assets/js/informe.js
// Objetivo:
// - Filtrar platos
// - Consultar por alérgenos
// - Filtrar tabla de alérgenos
// - Cambiar entre vista cliente e interna
// - Gestionar modos de impresión
// ==========================================================

document.addEventListener("DOMContentLoaded", function () {
    // ==========================================================
    // ELEMENTOS PRINCIPALES
    // ==========================================================

    const buscar = document.getElementById("buscar");
    const categoria = document.getElementById("categoria");
    const alergeno = document.getElementById("alergeno");
    const revision = document.getElementById("revision");

    const platos = Array.from(document.querySelectorAll(".js-plato"));
    const contadorVisibles = document.getElementById("contadorVisibles");

    // ==========================================================
    // ELEMENTOS DE CONSULTA POR ALÉRGENOS
    // ==========================================================

    const botonesAlergenos = Array.from(document.querySelectorAll(".js-allergy-chip"));
    const modoAlergenos = document.getElementById("modoAlergenos");
    const limpiarAlergenos = document.getElementById("limpiarAlergenos");
    const consultaAlergenosTexto = document.getElementById("consultaAlergenosTexto");

    let alergenosSeleccionados = [];

    // ==========================================================
    // ELEMENTOS DE TABLA / MATRIZ DE ALÉRGENOS
    // ==========================================================

    const buscarMatriz = document.getElementById("buscarMatriz");
    const soloConAlergenosMatriz = document.getElementById("soloConAlergenosMatriz");
    const filasMatriz = Array.from(document.querySelectorAll(".js-matriz-row"));
    const contadorMatriz = document.getElementById("contadorMatriz");

    // ==========================================================
    // ELEMENTOS DE IMPRESIÓN Y VISTA
    // ==========================================================

    const botonesImpresion = Array.from(document.querySelectorAll("[data-print-mode]"));
    const botonesModoVista = Array.from(document.querySelectorAll("[data-view-mode]"));
    const textoModoVista = document.getElementById("textoModoVista");

    // ==========================================================
    // FUNCIONES AUXILIARES
    // ==========================================================

    function normalizar(texto) {
        return (texto || "")
            .toString()
            .toLowerCase()
            .normalize("NFD")
            .replace(/[\u0300-\u036f]/g, "")
            .trim();
    }

    function capitalizar(texto) {
        if (!texto) {
            return "";
        }

        return texto.charAt(0).toUpperCase() + texto.slice(1);
    }

    function obtenerAlergenosSeleccionadosTexto() {
        return alergenosSeleccionados
            .map(function (alergenoSeleccionado) {
                return capitalizar(alergenoSeleccionado);
            })
            .join(", ");
    }

    // ==========================================================
    // CONSULTA POR ALÉRGENOS
    // ==========================================================

    function actualizarTextoConsultaAlergenos(visibles) {
        if (!consultaAlergenosTexto) {
            return;
        }

        if (alergenosSeleccionados.length === 0) {
            consultaAlergenosTexto.textContent = "Selecciona uno o varios alérgenos para iniciar la consulta.";
            return;
        }

        const textoAlergenos = obtenerAlergenosSeleccionadosTexto();
        const modo = modoAlergenos ? modoAlergenos.value : "contienen";

        if (modo === "aptos") {
            consultaAlergenosTexto.textContent =
                "Mostrando " + visibles + " platos aparentemente aptos para evitar: " + textoAlergenos + ". Revisa también posibles trazas o advertencias.";
        } else {
            consultaAlergenosTexto.textContent =
                "Mostrando " + visibles + " platos que contienen alguno de estos alérgenos: " + textoAlergenos + ".";
        }
    }

    function coincideModoConsultaAlergenos(alergenosPlato) {
        if (alergenosSeleccionados.length === 0) {
            return true;
        }

        const contieneAlguno = alergenosSeleccionados.some(function (alergenoSeleccionado) {
            return alergenosPlato.includes(alergenoSeleccionado);
        });

        const modo = modoAlergenos ? modoAlergenos.value : "contienen";

        if (modo === "aptos") {
            return !contieneAlguno;
        }

        return contieneAlguno;
    }

    // ==========================================================
    // FILTRADO DE PLATOS
    // ==========================================================

    function filtrarPlatos() {
        if (!contadorVisibles) {
            return;
        }

        const textoBuscar = buscar ? normalizar(buscar.value) : "";
        const categoriaSeleccionada = categoria ? normalizar(categoria.value) : "";
        const alergenoSeleccionado = alergeno ? normalizar(alergeno.value) : "";
        const revisionSeleccionada = revision ? normalizar(revision.value) : "";

        let visibles = 0;

        platos.forEach(function (plato) {
            const texto = normalizar(plato.dataset.text);
            const categoriaPlato = normalizar(plato.dataset.categoria);
            const alergenosPlato = normalizar(plato.dataset.alergenos);
            const revisionPlato = normalizar(plato.dataset.revision);

            const coincideTexto = textoBuscar === "" || texto.includes(textoBuscar);
            const coincideCategoria = categoriaSeleccionada === "" || categoriaPlato === categoriaSeleccionada;
            const coincideAlergenoSelect = alergenoSeleccionado === "" || alergenosPlato.includes(alergenoSeleccionado);
            const coincideRevision = revisionSeleccionada === "" || revisionPlato === revisionSeleccionada;
            const coincideConsultaAlergenos = coincideModoConsultaAlergenos(alergenosPlato);

            const visible =
                coincideTexto &&
                coincideCategoria &&
                coincideAlergenoSelect &&
                coincideRevision &&
                coincideConsultaAlergenos;

            plato.style.display = visible ? "" : "none";

            if (visible) {
                visibles++;
            }
        });

        contadorVisibles.textContent = visibles;
        actualizarTextoConsultaAlergenos(visibles);
    }

    // ==========================================================
    // FILTRADO DE TABLA / MATRIZ
    // ==========================================================

    function filtrarMatriz() {
        if (!buscarMatriz || !soloConAlergenosMatriz || !contadorMatriz) {
            return;
        }

        const textoBuscar = normalizar(buscarMatriz.value);
        const soloConAlergenos = soloConAlergenosMatriz.checked;

        let visibles = 0;

        filasMatriz.forEach(function (fila) {
            const textoFila = normalizar(fila.dataset.text);
            const tieneAlergenos = fila.dataset.tieneAlergenos === "si";

            const coincideTexto = textoBuscar === "" || textoFila.includes(textoBuscar);
            const coincideAlergenos = !soloConAlergenos || tieneAlergenos;

            const visible = coincideTexto && coincideAlergenos;

            fila.style.display = visible ? "" : "none";

            if (visible) {
                visibles++;
            }
        });

        contadorMatriz.textContent = visibles;
    }

    // ==========================================================
    // MODO VISTA CLIENTE / INTERNA
    // ==========================================================

    function cambiarModoVista(modo) {
        document.body.classList.remove("view-mode-client");
        document.body.classList.remove("view-mode-internal");

        if (modo === "client") {
            document.body.classList.add("view-mode-client");

            if (textoModoVista) {
                textoModoVista.textContent =
                    "Estás usando la vista cliente. Se ocultan revisión interna, fuentes técnicas, documentos procesados y detalles de desarrollo.";
            }
        } else {
            document.body.classList.add("view-mode-internal");

            if (textoModoVista) {
                textoModoVista.textContent =
                    "Estás usando la vista interna, pensada para revisar estados, documentos, fuentes y detalles técnicos.";
            }
        }

        botonesModoVista.forEach(function (boton) {
            boton.classList.toggle("is-active", boton.dataset.viewMode === modo);
        });

        localStorage.setItem("kitcherry_docs_view_mode", modo);

        filtrarPlatos();
        filtrarMatriz();
    }

    // ==========================================================
    // MODO IMPRESIÓN
    // ==========================================================

    function limpiarModosImpresion() {
        document.body.classList.remove("print-mode-carta");
        document.body.classList.remove("print-mode-matriz");
        document.body.classList.remove("print-mode-interno");
        document.body.classList.remove("print-mode-advertencias");
    }

    function activarModoImpresion(modo) {
        limpiarModosImpresion();

        if (modo === "carta") {
            document.body.classList.add("print-mode-carta");
        }

        if (modo === "matriz") {
            document.body.classList.add("print-mode-matriz");
        }

        if (modo === "interno") {
            document.body.classList.add("print-mode-interno");
        }

        if (modo === "advertencias") {
            document.body.classList.add("print-mode-advertencias");
        }

        window.print();
    }

    // ==========================================================
    // EVENTOS: CONSULTA POR ALÉRGENOS
    // ==========================================================

    botonesAlergenos.forEach(function (boton) {
        boton.addEventListener("click", function () {
            const alergenoSeleccionado = normalizar(boton.dataset.alergeno);

            if (alergenosSeleccionados.includes(alergenoSeleccionado)) {
                alergenosSeleccionados = alergenosSeleccionados.filter(function (item) {
                    return item !== alergenoSeleccionado;
                });

                boton.classList.remove("is-active");
            } else {
                alergenosSeleccionados.push(alergenoSeleccionado);
                boton.classList.add("is-active");
            }

            filtrarPlatos();
        });
    });

    if (limpiarAlergenos) {
        limpiarAlergenos.addEventListener("click", function () {
            alergenosSeleccionados = [];

            botonesAlergenos.forEach(function (boton) {
                boton.classList.remove("is-active");
            });

            filtrarPlatos();
        });
    }

    // ==========================================================
    // EVENTOS: FILTROS DE CARTA
    // ==========================================================

    if (buscar) {
        buscar.addEventListener("input", filtrarPlatos);
    }

    if (categoria) {
        categoria.addEventListener("change", filtrarPlatos);
    }

    if (alergeno) {
        alergeno.addEventListener("change", filtrarPlatos);
    }

    if (revision) {
        revision.addEventListener("change", filtrarPlatos);
    }

    if (modoAlergenos) {
        modoAlergenos.addEventListener("change", filtrarPlatos);
    }

    // ==========================================================
    // EVENTOS: FILTROS DE MATRIZ
    // ==========================================================

    if (buscarMatriz) {
        buscarMatriz.addEventListener("input", filtrarMatriz);
    }

    if (soloConAlergenosMatriz) {
        soloConAlergenosMatriz.addEventListener("change", filtrarMatriz);
    }

    // ==========================================================
    // EVENTOS: BOTONES DE IMPRESIÓN
    // ==========================================================

    botonesImpresion.forEach(function (boton) {
        boton.addEventListener("click", function () {
            const modo = boton.dataset.printMode;
            activarModoImpresion(modo);
        });
    });

    window.addEventListener("afterprint", function () {
        limpiarModosImpresion();
    });

    // ==========================================================
    // EVENTOS: BOTONES DE VISTA
    // ==========================================================

    botonesModoVista.forEach(function (boton) {
        boton.addEventListener("click", function () {
            cambiarModoVista(boton.dataset.viewMode);
        });
    });

    // ==========================================================
    // INICIO
    // ==========================================================

    const modoGuardado = localStorage.getItem("kitcherry_docs_view_mode") || "internal";

    if (modoGuardado === "client" || modoGuardado === "internal") {
        cambiarModoVista(modoGuardado);
    } else {
        cambiarModoVista("internal");
    }

    filtrarPlatos();
    filtrarMatriz();
});
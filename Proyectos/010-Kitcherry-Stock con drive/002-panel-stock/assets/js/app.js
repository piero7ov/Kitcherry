// ==========================================================
// KITCHERRY STOCK
// Archivo: assets/js/app.js
// Buscador y filtros del panel
// ==========================================================

document.addEventListener("DOMContentLoaded", function () {
    const buscador = document.getElementById("buscador");
    const filtroCategoria = document.getElementById("filtroCategoria");
    const filtroEstado = document.getElementById("filtroEstado");
    const btnLimpiar = document.getElementById("btnLimpiar");
    const tablaProductos = document.getElementById("tablaProductos");
    const contadorProductos = document.getElementById("contadorProductos");
    const sinResultados = document.getElementById("sinResultados");

    if (!tablaProductos) {
        return;
    }

    const filas = Array.from(tablaProductos.querySelectorAll("tr"));

    function normalizarTexto(texto) {
        return texto
            .toString()
            .toLowerCase()
            .normalize("NFD")
            .replace(/[\u0300-\u036f]/g, "")
            .trim();
    }

    function aplicarFiltros() {
        const textoBusqueda = normalizarTexto(buscador.value);
        const categoriaSeleccionada = normalizarTexto(filtroCategoria.value);
        const estadoSeleccionado = normalizarTexto(filtroEstado.value);

        let visibles = 0;

        filas.forEach(function (fila) {
            const nombre = normalizarTexto(fila.dataset.nombre || "");
            const categoria = normalizarTexto(fila.dataset.categoria || "");
            const estado = normalizarTexto(fila.dataset.estado || "");

            const coincideBusqueda = nombre.includes(textoBusqueda);
            const coincideCategoria = categoriaSeleccionada === "" || categoria === categoriaSeleccionada;
            const coincideEstado = estadoSeleccionado === "" || estado === estadoSeleccionado;

            if (coincideBusqueda && coincideCategoria && coincideEstado) {
                fila.style.display = "";
                visibles++;
            } else {
                fila.style.display = "none";
            }
        });

        contadorProductos.textContent = visibles + " registros";

        if (visibles === 0) {
            sinResultados.style.display = "block";
        } else {
            sinResultados.style.display = "none";
        }
    }

    function limpiarFiltros() {
        buscador.value = "";
        filtroCategoria.value = "";
        filtroEstado.value = "";

        aplicarFiltros();
    }

    buscador.addEventListener("input", aplicarFiltros);
    filtroCategoria.addEventListener("change", aplicarFiltros);
    filtroEstado.addEventListener("change", aplicarFiltros);
    btnLimpiar.addEventListener("click", limpiarFiltros);
});
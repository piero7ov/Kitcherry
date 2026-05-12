// ==========================================================
// KITCHERRY STOCK
// Archivo: assets/js/app.js
// Filtros y resumen de solicitud interna
// ==========================================================

document.addEventListener("DOMContentLoaded", function () {
    // ======================================================
    // ELEMENTOS DE FILTRO
    // ======================================================

    const buscador = document.getElementById("buscador");
    const filtroCategoria = document.getElementById("filtroCategoria");
    const filtroEstado = document.getElementById("filtroEstado");
    const btnLimpiar = document.getElementById("btnLimpiar");
    const tablaProductos = document.getElementById("tablaProductos");
    const contadorProductos = document.getElementById("contadorProductos");
    const sinResultados = document.getElementById("sinResultados");

    // ======================================================
    // ELEMENTOS DE SOLICITUD
    // ======================================================

    const listaSolicitud = document.getElementById("listaSolicitud");
    const totalProductosSolicitud = document.getElementById("totalProductosSolicitud");
    const totalUnidadesSolicitud = document.getElementById("totalUnidadesSolicitud");
    const totalCosteSolicitud = document.getElementById("totalCosteSolicitud");

    const empleadoSolicitud = document.getElementById("empleadoSolicitud");
    const zonaSolicitud = document.getElementById("zonaSolicitud");
    const prioridadSolicitud = document.getElementById("prioridadSolicitud");
    const observacionesSolicitud = document.getElementById("observacionesSolicitud");

    const btnGenerarSolicitud = document.getElementById("btnGenerarSolicitud");
    const btnVaciarSolicitud = document.getElementById("btnVaciarSolicitud");
    const resultadoSolicitud = document.getElementById("resultadoSolicitud");

    if (!tablaProductos) {
        return;
    }

    const filas = Array.from(tablaProductos.querySelectorAll("tr"));
    const solicitud = [];

    // ======================================================
    // FUNCIONES GENERALES
    // ======================================================

    function normalizarTexto(texto) {
        return texto
            .toString()
            .toLowerCase()
            .normalize("NFD")
            .replace(/[\u0300-\u036f]/g, "")
            .trim();
    }

    function convertirNumero(valor) {
        const numero = parseFloat(valor);

        if (Number.isNaN(numero)) {
            return 0;
        }

        return numero;
    }

    function formatoEurosJS(valor) {
        return valor.toLocaleString("es-ES", {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }) + " €";
    }

    function escaparHTML(texto) {
        return texto
            .toString()
            .replaceAll("&", "&amp;")
            .replaceAll("<", "&lt;")
            .replaceAll(">", "&gt;")
            .replaceAll('"', "&quot;")
            .replaceAll("'", "&#039;");
    }

    // ======================================================
    // FILTROS
    // ======================================================

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

    // ======================================================
    // SOLICITUD INTERNA
    // ======================================================

    function obtenerProductoSolicitud(id) {
        return solicitud.find(function (item) {
            return item.id === id;
        });
    }

    function agregarProductoSolicitud(datosProducto) {
        const productoExistente = obtenerProductoSolicitud(datosProducto.id);

        if (productoExistente) {
            productoExistente.cantidad += datosProducto.cantidad;
        } else {
            solicitud.push(datosProducto);
        }

        resultadoSolicitud.style.display = "none";
        resultadoSolicitud.textContent = "";

        renderizarSolicitud();
    }

    function eliminarProductoSolicitud(id) {
        const indice = solicitud.findIndex(function (item) {
            return item.id === id;
        });

        if (indice !== -1) {
            solicitud.splice(indice, 1);
        }

        renderizarSolicitud();
    }

    function vaciarSolicitud() {
        solicitud.length = 0;

        empleadoSolicitud.value = "";
        zonaSolicitud.value = "";
        prioridadSolicitud.value = "Normal";
        observacionesSolicitud.value = "";

        resultadoSolicitud.style.display = "none";
        resultadoSolicitud.textContent = "";

        renderizarSolicitud();
    }

    function calcularTotalesSolicitud() {
        let totalProductos = solicitud.length;
        let totalUnidades = 0;
        let totalCoste = 0;

        solicitud.forEach(function (item) {
            totalUnidades += item.cantidad;
            totalCoste += item.cantidad * item.coste;
        });

        return {
            productos: totalProductos,
            unidades: totalUnidades,
            coste: totalCoste
        };
    }

    function renderizarSolicitud() {
        const totales = calcularTotalesSolicitud();

        totalProductosSolicitud.textContent = totales.productos;
        totalUnidadesSolicitud.textContent = totales.unidades;
        totalCosteSolicitud.textContent = formatoEurosJS(totales.coste);

        if (solicitud.length === 0) {
            listaSolicitud.innerHTML = '<p class="texto-vacio">Todavía no se han añadido productos.</p>';
            return;
        }

        let html = "";

        solicitud.forEach(function (item) {
            html += `
                <div class="item-solicitud">
                    <div>
                        <strong>${escaparHTML(item.nombre)}</strong>
                        <span>${escaparHTML(item.categoria)} · ${formatoEurosJS(item.coste)} / ${escaparHTML(item.unidad)}</span>
                        <button type="button" class="btn-eliminar-item" data-id="${escaparHTML(item.id)}">
                            Quitar
                        </button>
                    </div>

                    <div class="cantidad-item">
                        ${item.cantidad} ${escaparHTML(item.unidad)}
                    </div>
                </div>
            `;
        });

        listaSolicitud.innerHTML = html;

        const botonesEliminar = listaSolicitud.querySelectorAll(".btn-eliminar-item");

        botonesEliminar.forEach(function (boton) {
            boton.addEventListener("click", function () {
                eliminarProductoSolicitud(boton.dataset.id);
            });
        });
    }

    function generarSolicitud() {
        if (solicitud.length === 0) {
            resultadoSolicitud.style.display = "block";
            resultadoSolicitud.textContent = "No se puede generar una solicitud sin productos seleccionados.";
            return;
        }

        const empleado = empleadoSolicitud.value.trim() || "Sin indicar";
        const zona = zonaSolicitud.value.trim() || "Sin indicar";
        const prioridad = prioridadSolicitud.value.trim() || "Normal";
        const observaciones = observacionesSolicitud.value.trim() || "Sin observaciones";
        const fecha = new Date().toLocaleString("es-ES");

        const totales = calcularTotalesSolicitud();

        let texto = "";
        texto += "SOLICITUD INTERNA DE REPOSICIÓN\n";
        texto += "================================\n";
        texto += "Fecha: " + fecha + "\n";
        texto += "Empleado: " + empleado + "\n";
        texto += "Zona solicitante: " + zona + "\n";
        texto += "Prioridad: " + prioridad + "\n";
        texto += "Estado: Pendiente\n\n";

        texto += "PRODUCTOS SOLICITADOS\n";
        texto += "--------------------------------\n";

        solicitud.forEach(function (item, index) {
            texto += (index + 1) + ". " + item.nombre + "\n";
            texto += "   Categoría: " + item.categoria + "\n";
            texto += "   Cantidad: " + item.cantidad + " " + item.unidad + "\n";
            texto += "   Coste estimado: " + formatoEurosJS(item.cantidad * item.coste) + "\n\n";
        });

        texto += "RESUMEN\n";
        texto += "--------------------------------\n";
        texto += "Productos distintos: " + totales.productos + "\n";
        texto += "Unidades totales: " + totales.unidades + "\n";
        texto += "Coste estimado total: " + formatoEurosJS(totales.coste) + "\n\n";

        texto += "OBSERVACIONES\n";
        texto += "--------------------------------\n";
        texto += observaciones;

        resultadoSolicitud.style.display = "block";
        resultadoSolicitud.textContent = texto;
    }

    // ======================================================
    // EVENTOS DE BOTONES AÑADIR
    // ======================================================

    const botonesSolicitar = document.querySelectorAll(".btn-solicitar");

    botonesSolicitar.forEach(function (boton) {
        boton.addEventListener("click", function () {
            const fila = boton.closest("tr");
            const inputCantidad = fila.querySelector(".input-cantidad");

            let cantidad = parseInt(inputCantidad.value, 10);

            if (Number.isNaN(cantidad) || cantidad < 1) {
                cantidad = 1;
                inputCantidad.value = 1;
            }

            const datosProducto = {
                id: boton.dataset.id,
                nombre: boton.dataset.nombre,
                categoria: boton.dataset.categoria,
                unidad: boton.dataset.unidad,
                coste: convertirNumero(boton.dataset.coste),
                cantidad: cantidad
            };

            agregarProductoSolicitud(datosProducto);

            const panelSolicitud = document.getElementById("solicitud");

            if (panelSolicitud) {
                panelSolicitud.scrollIntoView({
                    behavior: "smooth",
                    block: "start"
                });
            }
        });
    });

    btnVaciarSolicitud.addEventListener("click", vaciarSolicitud);
    btnGenerarSolicitud.addEventListener("click", generarSolicitud);

    renderizarSolicitud();
});
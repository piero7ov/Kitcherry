// ==========================================================
// KITCHERRY STOCK
// Archivo: assets/js/app.js
// Navegación, filtros, solicitudes, proveedores, email y gráficas
// ==========================================================

document.addEventListener("DOMContentLoaded", function () {

    // ======================================================
    // NAVEGACIÓN ENTRE SECCIONES DEL PANEL
    // ======================================================

    const enlacesMenu = document.querySelectorAll(".menu-lateral a[data-seccion]");
    const secciones = document.querySelectorAll(".seccion-panel");

    function mostrarSeccion(nombreSeccion) {
        secciones.forEach(function (seccion) {
            seccion.classList.remove("activa");
        });

        enlacesMenu.forEach(function (enlace) {
            enlace.classList.remove("activo");
        });

        const seccionActiva = document.getElementById("seccion-" + nombreSeccion);
        const enlaceActivo = document.querySelector('.menu-lateral a[data-seccion="' + nombreSeccion + '"]');

        if (seccionActiva) {
            seccionActiva.classList.add("activa");
        }

        if (enlaceActivo) {
            enlaceActivo.classList.add("activo");
        }

        window.scrollTo({
            top: 0,
            behavior: "smooth"
        });

        if (nombreSeccion === "dashboard") {
            setTimeout(renderizarGraficasDashboard, 80);
        }
    }

    enlacesMenu.forEach(function (enlace) {
        enlace.addEventListener("click", function (evento) {
            evento.preventDefault();

            const seccion = enlace.getAttribute("data-seccion");

            if (seccion) {
                mostrarSeccion(seccion);
            }
        });
    });

    // ======================================================
    // ELEMENTOS DE PRODUCTOS
    // ======================================================

    const buscador = document.getElementById("buscador");
    const filtroCategoria = document.getElementById("filtroCategoria");
    const filtroProveedor = document.getElementById("filtroProveedor");
    const filtroEstado = document.getElementById("filtroEstado");
    const btnLimpiar = document.getElementById("btnLimpiar");
    const tablaProductos = document.getElementById("tablaProductos");
    const contadorProductos = document.getElementById("contadorProductos");
    const sinResultados = document.getElementById("sinResultados");

    const filas = tablaProductos ? Array.from(tablaProductos.querySelectorAll("tr")) : [];

    // ======================================================
    // ELEMENTOS DE SOLICITUD DETALLADA
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

    // ======================================================
    // ELEMENTOS DE RESUMEN RÁPIDO EN PRODUCTOS
    // ======================================================

    const miniTotalProductosSolicitud = document.getElementById("miniTotalProductosSolicitud");
    const miniTotalUnidadesSolicitud = document.getElementById("miniTotalUnidadesSolicitud");
    const miniTotalCosteSolicitud = document.getElementById("miniTotalCosteSolicitud");
    const miniListaSolicitud = document.getElementById("miniListaSolicitud");
    const btnVerSolicitudMini = document.getElementById("btnVerSolicitudMini");
    const btnVaciarSolicitudMini = document.getElementById("btnVaciarSolicitudMini");

    const btnActualizarSolicitudes = document.getElementById("btnActualizarSolicitudes");

    // ======================================================
    // FILTROS DE SOLICITUDES
    // ======================================================

    const buscadorSolicitudes = document.getElementById("buscadorSolicitudes");
    const filtroEstadoSolicitud = document.getElementById("filtroEstadoSolicitud");
    const filtroPrioridadSolicitud = document.getElementById("filtroPrioridadSolicitud");
    const filtroZonaSolicitud = document.getElementById("filtroZonaSolicitud");
    const btnLimpiarFiltrosSolicitudes = document.getElementById("btnLimpiarFiltrosSolicitudes");
    const tablaSolicitudes = document.getElementById("tablaSolicitudes");
    const contadorSolicitudes = document.getElementById("contadorSolicitudes");
    const sinResultadosSolicitudes = document.getElementById("sinResultadosSolicitudes");

    const filasSolicitudes = tablaSolicitudes ? Array.from(tablaSolicitudes.querySelectorAll("tr")) : [];

    // ======================================================
    // ELEMENTOS CRUD PROVEEDORES
    // ======================================================

    const formProveedor = document.getElementById("formProveedor");
    const tituloFormularioProveedor = document.getElementById("tituloFormularioProveedor");
    const modoProveedor = document.getElementById("modoProveedor");

    const idProveedor = document.getElementById("idProveedor");
    const nombreProveedor = document.getElementById("nombreProveedor");
    const tipoProveedor = document.getElementById("tipoProveedor");
    const emailProveedor = document.getElementById("emailProveedor");
    const telefonoProveedor = document.getElementById("telefonoProveedor");
    const ubicacionProveedor = document.getElementById("ubicacionProveedor");
    const entregaProveedor = document.getElementById("entregaProveedor");
    const conservacionProveedor = document.getElementById("conservacionProveedor");
    const activoProveedor = document.getElementById("activoProveedor");
    const observacionesProveedor = document.getElementById("observacionesProveedor");

    const btnGuardarProveedor = document.getElementById("btnGuardarProveedor");
    const btnCancelarEdicionProveedor = document.getElementById("btnCancelarEdicionProveedor");
    const btnActualizarProveedores = document.getElementById("btnActualizarProveedores");
    const resultadoProveedor = document.getElementById("resultadoProveedor");

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

    function generarIdLocalSolicitud() {
        const fecha = new Date();
        const anio = fecha.getFullYear();
        const mes = String(fecha.getMonth() + 1).padStart(2, "0");
        const dia = String(fecha.getDate()).padStart(2, "0");
        const hora = String(fecha.getHours()).padStart(2, "0");
        const minuto = String(fecha.getMinutes()).padStart(2, "0");
        const segundo = String(fecha.getSeconds()).padStart(2, "0");
        const aleatorio = Math.floor(Math.random() * 900) + 100;

        return `SOL-${anio}${mes}${dia}-${hora}${minuto}${segundo}-${aleatorio}`;
    }

    function recargarEnSeccion(seccion) {
        localStorage.setItem("kitcherry_seccion_activa", seccion);
        window.location.reload();
    }

    // ======================================================
    // FILTROS DE PRODUCTOS
    // ======================================================

    function aplicarFiltros() {
        const textoBusqueda = normalizarTexto(buscador.value);
        const categoriaSeleccionada = normalizarTexto(filtroCategoria.value);
        const proveedorSeleccionado = normalizarTexto(filtroProveedor.value);
        const estadoSeleccionado = normalizarTexto(filtroEstado.value);

        let visibles = 0;

        filas.forEach(function (fila) {
            const nombre = normalizarTexto(fila.dataset.nombre || "");
            const categoria = normalizarTexto(fila.dataset.categoria || "");
            const proveedor = normalizarTexto(fila.dataset.proveedor || "");
            const estado = normalizarTexto(fila.dataset.estado || "");

            const coincideBusqueda = nombre.includes(textoBusqueda);
            const coincideCategoria = categoriaSeleccionada === "" || categoria === categoriaSeleccionada;
            const coincideProveedor = proveedorSeleccionado === "" || proveedor === proveedorSeleccionado;
            const coincideEstado = estadoSeleccionado === "" || estado === estadoSeleccionado;

            if (coincideBusqueda && coincideCategoria && coincideProveedor && coincideEstado) {
                fila.style.display = "";
                visibles++;
            } else {
                fila.style.display = "none";
            }
        });

        if (contadorProductos) {
            contadorProductos.textContent = visibles + " registros";
        }

        if (sinResultados) {
            sinResultados.style.display = visibles === 0 ? "block" : "none";
        }
    }

    function limpiarFiltros() {
        buscador.value = "";
        filtroCategoria.value = "";
        filtroProveedor.value = "";
        filtroEstado.value = "";

        aplicarFiltros();
    }

    if (buscador && filtroCategoria && filtroProveedor && filtroEstado && btnLimpiar) {
        buscador.addEventListener("input", aplicarFiltros);
        filtroCategoria.addEventListener("change", aplicarFiltros);
        filtroProveedor.addEventListener("change", aplicarFiltros);
        filtroEstado.addEventListener("change", aplicarFiltros);
        btnLimpiar.addEventListener("click", limpiarFiltros);
    }

    // ======================================================
    // FILTROS DE SOLICITUDES
    // ======================================================

    function aplicarFiltrosSolicitudes() {
        const textoBusqueda = normalizarTexto(buscadorSolicitudes.value);
        const estadoSeleccionado = normalizarTexto(filtroEstadoSolicitud.value);
        const prioridadSeleccionada = normalizarTexto(filtroPrioridadSolicitud.value);
        const zonaSeleccionada = normalizarTexto(filtroZonaSolicitud.value);

        let visibles = 0;

        filasSolicitudes.forEach(function (fila) {
            const id = normalizarTexto(fila.dataset.idSolicitud || "");
            const empleado = normalizarTexto(fila.dataset.empleado || "");
            const zona = normalizarTexto(fila.dataset.zona || "");
            const estado = normalizarTexto(fila.dataset.estado || "");
            const prioridad = normalizarTexto(fila.dataset.prioridad || "");

            const coincideBusqueda =
                id.includes(textoBusqueda) ||
                empleado.includes(textoBusqueda) ||
                zona.includes(textoBusqueda);

            const coincideEstado = estadoSeleccionado === "" || estado === estadoSeleccionado;
            const coincidePrioridad = prioridadSeleccionada === "" || prioridad === prioridadSeleccionada;
            const coincideZona = zonaSeleccionada === "" || zona === zonaSeleccionada;

            if (coincideBusqueda && coincideEstado && coincidePrioridad && coincideZona) {
                fila.style.display = "";
                visibles++;
            } else {
                fila.style.display = "none";
            }
        });

        if (contadorSolicitudes) {
            contadorSolicitudes.textContent = visibles + " solicitudes";
        }

        if (sinResultadosSolicitudes) {
            sinResultadosSolicitudes.style.display = visibles === 0 ? "block" : "none";
        }
    }

    function limpiarFiltrosSolicitudes() {
        buscadorSolicitudes.value = "";
        filtroEstadoSolicitud.value = "";
        filtroPrioridadSolicitud.value = "";
        filtroZonaSolicitud.value = "";

        aplicarFiltrosSolicitudes();
    }

    if (
        buscadorSolicitudes &&
        filtroEstadoSolicitud &&
        filtroPrioridadSolicitud &&
        filtroZonaSolicitud &&
        btnLimpiarFiltrosSolicitudes
    ) {
        buscadorSolicitudes.addEventListener("input", aplicarFiltrosSolicitudes);
        filtroEstadoSolicitud.addEventListener("change", aplicarFiltrosSolicitudes);
        filtroPrioridadSolicitud.addEventListener("change", aplicarFiltrosSolicitudes);
        filtroZonaSolicitud.addEventListener("change", aplicarFiltrosSolicitudes);
        btnLimpiarFiltrosSolicitudes.addEventListener("click", limpiarFiltrosSolicitudes);
    }

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

        if (resultadoSolicitud) {
            resultadoSolicitud.style.display = "none";
            resultadoSolicitud.textContent = "";
        }

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

        if (empleadoSolicitud) empleadoSolicitud.value = "";
        if (zonaSolicitud) zonaSolicitud.value = "";
        if (prioridadSolicitud) prioridadSolicitud.value = "Normal";
        if (observacionesSolicitud) observacionesSolicitud.value = "";

        if (resultadoSolicitud) {
            resultadoSolicitud.style.display = "none";
            resultadoSolicitud.textContent = "";
        }

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

    function renderizarResumenRapido(totales) {
        if (miniTotalProductosSolicitud) {
            miniTotalProductosSolicitud.textContent = totales.productos;
        }

        if (miniTotalUnidadesSolicitud) {
            miniTotalUnidadesSolicitud.textContent = totales.unidades;
        }

        if (miniTotalCosteSolicitud) {
            miniTotalCosteSolicitud.textContent = formatoEurosJS(totales.coste);
        }

        if (!miniListaSolicitud) {
            return;
        }

        if (solicitud.length === 0) {
            miniListaSolicitud.textContent = "No hay productos añadidos todavía.";
            return;
        }

        const maxItems = 5;
        const visibles = solicitud.slice(0, maxItems);

        let html = "<ul>";

        visibles.forEach(function (item) {
            html += `
                <li>
                    ${escaparHTML(item.nombre)}
                    ·
                    <strong>${item.cantidad} ${escaparHTML(item.unidad)}</strong>
                </li>
            `;
        });

        if (solicitud.length > maxItems) {
            html += `
                <li class="mini-extra">
                    +${solicitud.length - maxItems} productos más
                </li>
            `;
        }

        html += "</ul>";

        miniListaSolicitud.innerHTML = html;
    }

    function renderizarSolicitud() {
        const totales = calcularTotalesSolicitud();

        renderizarResumenRapido(totales);

        if (totalProductosSolicitud) {
            totalProductosSolicitud.textContent = totales.productos;
        }

        if (totalUnidadesSolicitud) {
            totalUnidadesSolicitud.textContent = totales.unidades;
        }

        if (totalCosteSolicitud) {
            totalCosteSolicitud.textContent = formatoEurosJS(totales.coste);
        }

        if (!listaSolicitud) {
            return;
        }

        if (solicitud.length === 0) {
            listaSolicitud.innerHTML = '<p class="texto-vacio">Añade productos desde la sección Productos.</p>';
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

    function crearResumenProductosTexto() {
        return solicitud.map(function (item) {
            const costeLinea = item.cantidad * item.coste;

            return `${item.nombre} | ${item.categoria} | ${item.cantidad} ${item.unidad} | ${formatoEurosJS(costeLinea)}`;
        }).join(" || ");
    }

    function crearTextoSolicitud(datos, totales) {
        let texto = "";

        texto += "SOLICITUD INTERNA DE REPOSICIÓN\n";
        texto += "================================\n";
        texto += "ID: " + datos.id_solicitud + "\n";
        texto += "Fecha: " + datos.fecha + "\n";
        texto += "Empleado: " + datos.empleado + "\n";
        texto += "Zona solicitante: " + datos.zona + "\n";
        texto += "Prioridad: " + datos.prioridad + "\n";
        texto += "Estado: " + datos.estado + "\n\n";

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
        texto += datos.observaciones;

        return texto;
    }

    async function generarSolicitud() {
        if (solicitud.length === 0) {
            resultadoSolicitud.style.display = "block";
            resultadoSolicitud.textContent = "No se puede generar una solicitud sin productos seleccionados.";
            return;
        }

        const totales = calcularTotalesSolicitud();

        const datos = {
            id_solicitud: generarIdLocalSolicitud(),
            fecha: new Date().toLocaleString("es-ES"),
            empleado: empleadoSolicitud.value.trim() || "Sin indicar",
            zona: zonaSolicitud.value.trim() || "Sin indicar",
            prioridad: prioridadSolicitud.value.trim() || "Normal",
            estado: "Pendiente",
            total_productos: totales.productos,
            total_unidades: totales.unidades,
            coste_estimado: totales.coste.toFixed(2),
            observaciones: observacionesSolicitud.value.trim() || "Sin observaciones",
            productos: crearResumenProductosTexto()
        };

        const textoSolicitud = crearTextoSolicitud(datos, totales);

        resultadoSolicitud.style.display = "block";
        resultadoSolicitud.textContent = "Guardando solicitud en Google Sheets...";

        btnGenerarSolicitud.disabled = true;
        btnGenerarSolicitud.textContent = "Guardando...";

        try {
            const respuesta = await fetch("api_guardar_solicitud.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify(datos)
            });

            const resultado = await respuesta.json();

            if (!resultado.ok) {
                resultadoSolicitud.textContent = "No se pudo guardar la solicitud.\n\n" + resultado.mensaje;
                return;
            }

            resultadoSolicitud.textContent =
                "Solicitud guardada correctamente en Google Sheets.\n" +
                "Pulsa el botón \"Actualizar solicitudes\" para verla en el historial.\n\n" +
                textoSolicitud;

        } catch (error) {
            resultadoSolicitud.textContent =
                "Error al guardar la solicitud.\n\n" +
                error.message;
        } finally {
            btnGenerarSolicitud.disabled = false;
            btnGenerarSolicitud.textContent = "Generar solicitud";
        }
    }

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
        });
    });

    if (btnVerSolicitudMini) {
        btnVerSolicitudMini.addEventListener("click", function () {
            mostrarSeccion("solicitudes");
        });
    }

    if (btnVaciarSolicitudMini) {
        btnVaciarSolicitudMini.addEventListener("click", vaciarSolicitud);
    }

    if (btnVaciarSolicitud) {
        btnVaciarSolicitud.addEventListener("click", vaciarSolicitud);
    }

    if (btnActualizarSolicitudes) {
        btnActualizarSolicitudes.addEventListener("click", function () {
            recargarEnSeccion("solicitudes");
        });
    }

    if (btnGenerarSolicitud) {
        btnGenerarSolicitud.addEventListener("click", generarSolicitud);
    }

    // ======================================================
    // ACTUALIZAR ESTADO DE SOLICITUD
    // ======================================================

    const botonesActualizarEstadoSolicitud = document.querySelectorAll(".btn-actualizar-estado-solicitud");

    botonesActualizarEstadoSolicitud.forEach(function (boton) {
        boton.addEventListener("click", async function () {
            const fila = boton.closest("tr");
            const idSolicitud = boton.dataset.idSolicitud || "";
            const selectEstado = fila.querySelector(".select-estado-solicitud");
            const nuevoEstado = selectEstado ? selectEstado.value : "";

            if (idSolicitud === "" || nuevoEstado === "") {
                alert("No se ha podido obtener la solicitud o el estado.");
                return;
            }

            const confirmar = confirm(
                "¿Quieres cambiar el estado de la solicitud " + idSolicitud + " a \"" + nuevoEstado + "\"?"
            );

            if (!confirmar) {
                return;
            }

            boton.disabled = true;
            boton.textContent = "Actualizando...";

            try {
                const respuesta = await fetch("api_actualizar_solicitud.php", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json"
                    },
                    body: JSON.stringify({
                        id_solicitud: idSolicitud,
                        estado: nuevoEstado
                    })
                });

                const resultado = await respuesta.json();

                if (!resultado.ok) {
                    alert("No se pudo actualizar el estado.\n\n" + resultado.mensaje);
                    return;
                }

                recargarEnSeccion("solicitudes");

            } catch (error) {
                alert("Error al actualizar el estado.\n\n" + error.message);
            } finally {
                boton.disabled = false;
                boton.textContent = "Actualizar estado";
            }
        });
    });

    // ======================================================
    // CRUD PROVEEDORES
    // ======================================================

    function limpiarFormularioProveedor() {
        if (!formProveedor) {
            return;
        }

        modoProveedor.value = "crear";
        idProveedor.value = "";
        nombreProveedor.value = "";
        tipoProveedor.value = "";
        emailProveedor.value = "";
        telefonoProveedor.value = "";
        ubicacionProveedor.value = "";
        entregaProveedor.value = "";
        conservacionProveedor.value = "";
        activoProveedor.value = "Sí";
        observacionesProveedor.value = "";

        tituloFormularioProveedor.textContent = "Nuevo proveedor";
        btnGuardarProveedor.textContent = "Guardar proveedor";

        resultadoProveedor.style.display = "none";
        resultadoProveedor.textContent = "";
    }

    function obtenerDatosFormularioProveedor() {
        return {
            id_proveedor: idProveedor.value.trim(),
            nombre_proveedor: nombreProveedor.value.trim(),
            tipo: tipoProveedor.value.trim(),
            email: emailProveedor.value.trim(),
            telefono: telefonoProveedor.value.trim(),
            ubicacion: ubicacionProveedor.value.trim(),
            tiempo_entrega_estimado: entregaProveedor.value.trim(),
            tipo_conservacion: conservacionProveedor.value.trim(),
            activo: activoProveedor.value.trim(),
            observaciones: observacionesProveedor.value.trim()
        };
    }

    function cargarProveedorEnFormulario(fila) {
        modoProveedor.value = "editar";

        idProveedor.value = fila.dataset.idProveedor || "";
        nombreProveedor.value = fila.dataset.nombreProveedor || "";
        tipoProveedor.value = fila.dataset.tipo || "";
        emailProveedor.value = fila.dataset.email || "";
        telefonoProveedor.value = fila.dataset.telefono || "";
        ubicacionProveedor.value = fila.dataset.ubicacion || "";
        entregaProveedor.value = fila.dataset.entrega || "";
        conservacionProveedor.value = fila.dataset.conservacion || "";
        activoProveedor.value = fila.dataset.activo || "Sí";
        observacionesProveedor.value = fila.dataset.observaciones || "";

        tituloFormularioProveedor.textContent = "Editar proveedor";
        btnGuardarProveedor.textContent = "Actualizar proveedor";

        resultadoProveedor.style.display = "none";
        resultadoProveedor.textContent = "";

        window.scrollTo({
            top: 0,
            behavior: "smooth"
        });
    }

    async function enviarProveedor(accion, proveedor) {
        const respuesta = await fetch("api_proveedores.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify({
                accion: accion,
                proveedor: proveedor
            })
        });

        return await respuesta.json();
    }

    async function guardarProveedor(evento) {
        evento.preventDefault();

        const proveedor = obtenerDatosFormularioProveedor();

        if (proveedor.nombre_proveedor === "") {
            resultadoProveedor.style.display = "block";
            resultadoProveedor.textContent = "El nombre del proveedor es obligatorio.";
            return;
        }

        const accion = modoProveedor.value === "editar" ? "editar_proveedor" : "crear_proveedor";

        resultadoProveedor.style.display = "block";
        resultadoProveedor.textContent = "Guardando proveedor en Google Sheets...";

        btnGuardarProveedor.disabled = true;
        btnGuardarProveedor.textContent = "Guardando...";

        try {
            const resultado = await enviarProveedor(accion, proveedor);

            if (!resultado.ok) {
                resultadoProveedor.textContent = "No se pudo guardar el proveedor.\n\n" + resultado.mensaje;
                return;
            }

            resultadoProveedor.textContent =
                resultado.mensaje +
                "\nPulsa \"Actualizar proveedores\" para ver los cambios.";

        } catch (error) {
            resultadoProveedor.textContent =
                "Error al guardar el proveedor.\n\n" +
                error.message;
        } finally {
            btnGuardarProveedor.disabled = false;
            btnGuardarProveedor.textContent = modoProveedor.value === "editar" ? "Actualizar proveedor" : "Guardar proveedor";
        }
    }

    async function desactivarProveedor(fila) {
        const id = fila.dataset.idProveedor || "";
        const nombre = fila.dataset.nombreProveedor || "";

        if (id === "") {
            return;
        }

        const confirmar = confirm("¿Seguro que quieres desactivar el proveedor " + nombre + "?");

        if (!confirmar) {
            return;
        }

        resultadoProveedor.style.display = "block";
        resultadoProveedor.textContent = "Desactivando proveedor en Google Sheets...";

        try {
            const resultado = await enviarProveedor("desactivar_proveedor", {
                id_proveedor: id
            });

            if (!resultado.ok) {
                resultadoProveedor.textContent = "No se pudo desactivar el proveedor.\n\n" + resultado.mensaje;
                return;
            }

            resultadoProveedor.textContent =
                resultado.mensaje +
                "\nPulsa \"Actualizar proveedores\" para ver los cambios.";

        } catch (error) {
            resultadoProveedor.textContent =
                "Error al desactivar el proveedor.\n\n" +
                error.message;
        }
    }

    const botonesEditarProveedor = document.querySelectorAll(".btn-editar-proveedor");
    const botonesDesactivarProveedor = document.querySelectorAll(".btn-desactivar-proveedor");

    botonesEditarProveedor.forEach(function (boton) {
        boton.addEventListener("click", function () {
            const fila = boton.closest("tr");
            cargarProveedorEnFormulario(fila);
        });
    });

    botonesDesactivarProveedor.forEach(function (boton) {
        boton.addEventListener("click", function () {
            const fila = boton.closest("tr");
            desactivarProveedor(fila);
        });
    });

    if (formProveedor) {
        formProveedor.addEventListener("submit", guardarProveedor);
    }

    if (btnCancelarEdicionProveedor) {
        btnCancelarEdicionProveedor.addEventListener("click", limpiarFormularioProveedor);
    }

    if (btnActualizarProveedores) {
        btnActualizarProveedores.addEventListener("click", function () {
            recargarEnSeccion("proveedores");
        });
    }

    // ======================================================
    // ENVÍO DE SOLICITUD POR EMAIL
    // ======================================================

    const botonesEnviarSolicitud = document.querySelectorAll(".btn-enviar-solicitud");

    botonesEnviarSolicitud.forEach(function (boton) {
        boton.addEventListener("click", async function () {
            const idSolicitud = boton.dataset.idSolicitud || "";

            if (idSolicitud === "") {
                alert("No se ha encontrado el ID de la solicitud.");
                return;
            }

            const destinatario = prompt(
                "Introduce el correo al que quieres enviar la solicitud " + idSolicitud + ":"
            );

            if (destinatario === null) {
                return;
            }

            const correoLimpio = destinatario.trim();

            if (correoLimpio === "") {
                alert("Debes introducir un correo destinatario.");
                return;
            }

            const confirmar = confirm(
                "¿Quieres enviar la solicitud " + idSolicitud + " a este correo?\n\n" + correoLimpio
            );

            if (!confirmar) {
                return;
            }

            boton.disabled = true;
            boton.textContent = "Enviando...";

            try {
                const respuesta = await fetch("api_enviar_solicitud.php", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json"
                    },
                    body: JSON.stringify({
                        id_solicitud: idSolicitud,
                        destinatario: correoLimpio
                    })
                });

                const resultado = await respuesta.json();

                if (!resultado.ok) {
                    alert("No se pudo enviar la solicitud.\n\n" + resultado.mensaje);
                    return;
                }

                alert("Solicitud enviada correctamente a:\n" + resultado.destinatario);

            } catch (error) {
                alert("Error al enviar la solicitud.\n\n" + error.message);
            } finally {
                boton.disabled = false;
                boton.textContent = "Enviar email";
            }
        });
    });

    // ======================================================
    // GRÁFICAS DASHBOARD
    // ======================================================

    function obtenerDatosGraficas() {
        const scriptDatos = document.getElementById("datosGraficasDashboard");

        if (!scriptDatos) {
            return null;
        }

        try {
            return JSON.parse(scriptDatos.textContent);
        } catch (error) {
            return null;
        }
    }

    function dibujarGraficaBarras(idCanvas, datos, opciones) {
        const canvas = document.getElementById(idCanvas);

        if (!canvas || !datos || datos.length === 0) {
            return;
        }

        const ctx = canvas.getContext("2d");
        const ancho = canvas.width;
        const alto = canvas.height;

        ctx.clearRect(0, 0, ancho, alto);

        const margen = {
            arriba: 26,
            derecha: 26,
            abajo: 54,
            izquierda: 48
        };

        const anchoGrafica = ancho - margen.izquierda - margen.derecha;
        const altoGrafica = alto - margen.arriba - margen.abajo;

        const maximo = Math.max(...datos.map(item => item.value), 1);
        const anchoBarra = anchoGrafica / datos.length * 0.52;
        const separacion = anchoGrafica / datos.length;

        ctx.font = "12px Arial";
        ctx.textAlign = "right";
        ctx.fillStyle = "#555555";

        for (let i = 0; i <= 4; i++) {
            const valor = Math.round((maximo / 4) * i);
            const y = margen.arriba + altoGrafica - (altoGrafica / 4) * i;

            ctx.strokeStyle = "#eeeeee";
            ctx.beginPath();
            ctx.moveTo(margen.izquierda, y);
            ctx.lineTo(ancho - margen.derecha, y);
            ctx.stroke();

            ctx.fillText(valor.toString(), margen.izquierda - 8, y + 4);
        }

        datos.forEach(function (item, indice) {
            const valor = item.value;
            const alturaBarra = (valor / maximo) * altoGrafica;
            const x = margen.izquierda + indice * separacion + (separacion - anchoBarra) / 2;
            const y = margen.arriba + altoGrafica - alturaBarra;

            ctx.fillStyle = opciones.colores[indice % opciones.colores.length];
            ctx.fillRect(x, y, anchoBarra, alturaBarra);

            ctx.fillStyle = "#161616";
            ctx.font = "bold 13px Arial";
            ctx.textAlign = "center";
            ctx.fillText(valor.toString(), x + anchoBarra / 2, y - 8);

            ctx.fillStyle = "#555555";
            ctx.font = "12px Arial";
            ctx.save();
            ctx.translate(x + anchoBarra / 2, alto - 16);
            ctx.rotate(-0.25);
            ctx.fillText(item.label, 0, 0);
            ctx.restore();
        });

        ctx.strokeStyle = "#cfcfcf";
        ctx.beginPath();
        ctx.moveTo(margen.izquierda, margen.arriba);
        ctx.lineTo(margen.izquierda, margen.arriba + altoGrafica);
        ctx.lineTo(ancho - margen.derecha, margen.arriba + altoGrafica);
        ctx.stroke();
    }

    function renderizarGraficasDashboard() {
        const datos = obtenerDatosGraficas();

        if (!datos) {
            return;
        }

        dibujarGraficaBarras("graficaStockEstados", datos.stockEstados, {
            colores: ["#15803d", "#C2182B", "#1d4ed8", "#555555"]
        });

        dibujarGraficaBarras("graficaSolicitudesPrioridad", datos.solicitudesPrioridad, {
            colores: ["#555555", "#b45309", "#C2182B"]
        });

        dibujarGraficaBarras("graficaSolicitudesEstado", datos.solicitudesEstado, {
            colores: ["#C2182B", "#1d4ed8", "#15803d", "#555555"]
        });
    }

    window.addEventListener("resize", function () {
        renderizarGraficasDashboard();
    });

    // ======================================================
    // RESTAURAR SECCIÓN ACTIVA TRAS RECARGA
    // ======================================================

    const seccionGuardada = localStorage.getItem("kitcherry_seccion_activa");

    if (seccionGuardada) {
        localStorage.removeItem("kitcherry_seccion_activa");
        mostrarSeccion(seccionGuardada);
    }

    renderizarSolicitud();
    limpiarFormularioProveedor();
    renderizarGraficasDashboard();
});